<?php
declare(strict_types=1);

namespace unit_tests\review;

use PHPUnit\Framework\TestCase;

/**
 * Tests for review rate limiting (max 3 reviews per user per hour).
 */
class ReviewRateLimitTest extends TestCase
{
    /** Simulated cache: key => [timestamps] */
    private array $cache = [];

    private const MAX_ATTEMPTS = 3;
    private const WINDOW_SECONDS = 3600; // 1 hour

    /**
     * Simulates the rate limit check.
     *
     * @throws \RuntimeException when rate limit exceeded
     */
    private function checkRateLimit(int $userId, int $now): void
    {
        $cacheKey = "rate_limit:reviews:{$userId}";

        $attempts = $this->cache[$cacheKey] ?? [];
        $windowStart = $now - self::WINDOW_SECONDS;

        // Prune old timestamps
        $attempts = array_values(array_filter($attempts, fn(int $ts) => $ts > $windowStart));

        if (count($attempts) >= self::MAX_ATTEMPTS) {
            throw new \RuntimeException('Rate limit exceeded', 42901);
        }

        $attempts[] = $now;
        $this->cache[$cacheKey] = $attempts;
    }

    public function test_first_3_reviews_in_hour_succeed(): void
    {
        $now = time();

        $this->checkRateLimit(1, $now);
        $this->checkRateLimit(1, $now + 60);
        $this->checkRateLimit(1, $now + 120);

        // All three should have been recorded
        $this->assertCount(3, $this->cache['rate_limit:reviews:1']);
    }

    public function test_4th_review_in_hour_throws_rate_limit_exception(): void
    {
        $now = time();

        $this->checkRateLimit(1, $now);
        $this->checkRateLimit(1, $now + 60);
        $this->checkRateLimit(1, $now + 120);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(42901);
        $this->checkRateLimit(1, $now + 180); // 4th attempt
    }

    public function test_review_after_window_resets_succeeds(): void
    {
        $now = time();

        // Fill up the window
        $this->checkRateLimit(1, $now);
        $this->checkRateLimit(1, $now + 60);
        $this->checkRateLimit(1, $now + 120);

        // After the window resets (1 hour + 1 second later)
        $afterWindow = $now + self::WINDOW_SECONDS + 1;
        $this->checkRateLimit(1, $afterWindow); // Should succeed

        // Old timestamps should be pruned; only the new one remains
        $this->assertCount(1, $this->cache['rate_limit:reviews:1']);
    }
}
