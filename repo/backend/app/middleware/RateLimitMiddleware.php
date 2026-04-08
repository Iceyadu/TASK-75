<?php
declare(strict_types=1);

namespace app\middleware;

use app\exception\RateLimitException;
use think\facade\Cache;
use think\Request;
use think\Response;

/**
 * Sliding-window rate limiter middleware.
 *
 * Configured via route parameters:
 *   ->middleware('rate_limit:reviews,3,60')
 *   // resource = "reviews", maxAttempts = 3, windowMinutes = 60
 *
 * Each request timestamp is stored in a cache list keyed to the resource and
 * the authenticated user.  Timestamps older than the window are pruned on
 * every request.
 */
class RateLimitMiddleware
{
    /**
     * @param Request  $request
     * @param \Closure $next
     * @param string   $resource       Logical resource name for the cache key.
     * @param int      $maxAttempts    Maximum allowed requests within the window.
     * @param int      $windowMinutes  Size of the sliding window in minutes.
     * @return Response
     * @throws RateLimitException
     */
    public function handle(
        Request $request,
        \Closure $next,
        string $resource = 'default',
        int $maxAttempts = 60,
        int $windowMinutes = 1
    ): Response {
        $userId       = $request->user->id;
        $cacheKey     = "rate_limit:{$resource}:{$userId}";
        $windowSeconds = $windowMinutes * 60;
        $now          = time();
        $windowStart  = $now - $windowSeconds;

        // Retrieve existing timestamps and prune those outside the window.
        /** @var int[] $attempts */
        $attempts = Cache::get($cacheKey, []);
        $attempts = array_values(array_filter($attempts, function (int $ts) use ($windowStart): bool {
            return $ts > $windowStart;
        }));

        // Check whether the limit has been reached.
        if (count($attempts) >= $maxAttempts) {
            // The oldest attempt in the window determines when capacity reopens.
            $oldestAttempt = min($attempts);
            $retryAfter    = ($oldestAttempt + $windowSeconds) - $now;

            throw new RateLimitException(
                "Rate limit exceeded for {$resource}. Try again in {$retryAfter} seconds.",
                42901,
                max(1, $retryAfter)
            );
        }

        // Record this attempt.
        $attempts[] = $now;
        Cache::set($cacheKey, $attempts, $windowSeconds);

        /** @var Response $response */
        $response = $next($request);

        // Attach informational rate-limit headers.
        $response->header([
            'X-RateLimit-Limit'     => (string) $maxAttempts,
            'X-RateLimit-Remaining' => (string) ($maxAttempts - count($attempts)),
            'X-RateLimit-Reset'     => (string) ($now + $windowSeconds),
        ]);

        return $response;
    }
}
