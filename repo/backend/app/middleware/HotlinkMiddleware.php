<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;

/**
 * Hotlink protection middleware for media URLs.
 *
 * Two-layer protection:
 *  1. Signed URL – the request must carry a valid HMAC signature and a
 *     non-expired timestamp.
 *  2. Referer check – if a Referer header is present its host must appear in
 *     the HOTLINK_ALLOWED_DOMAINS environment variable.  When the header is
 *     absent the signed URL alone is considered sufficient.
 */
class HotlinkMiddleware
{
    /**
     * @param Request  $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // ── 1. Validate signed URL ──────────────────────────────────────
        $signature = $request->param('signature', '');
        $expires   = $request->param('expires', '');

        if ($signature === '' || $expires === '') {
            return json([
                'code'    => 40301,
                'message' => 'Invalid media URL',
                'data'    => null,
                'errors'  => null,
            ], 403);
        }

        if ((int) $expires < time()) {
            return json([
                'code'    => 40302,
                'message' => 'Media URL has expired',
                'data'    => null,
                'errors'  => null,
            ], 403);
        }

        $mediaId = $request->route('id');
        $secret  = env('MEDIA_SIGN_SECRET', '');

        $expectedSignature = hash_hmac('sha256', $mediaId . $expires, (string) $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return json([
                'code'    => 40303,
                'message' => 'Invalid signature',
                'data'    => null,
                'errors'  => null,
            ], 403);
        }

        // ── 2. Referer check ────────────────────────────────────────────
        $referer = $request->header('Referer', '');

        if ($referer !== '') {
            $refererHost    = parse_url($referer, PHP_URL_HOST);
            $allowedRaw     = env('HOTLINK_ALLOWED_DOMAINS', '');
            $allowedDomains = array_filter(array_map('trim', explode(',', (string) $allowedRaw)));

            if (!empty($allowedDomains) && !in_array($refererHost, $allowedDomains, true)) {
                return json([
                    'code'    => 40304,
                    'message' => 'Hotlink protection: access denied',
                    'data'    => null,
                    'errors'  => null,
                ], 403);
            }
        }

        return $next($request);
    }
}
