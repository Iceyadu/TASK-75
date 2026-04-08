<?php
declare(strict_types=1);

namespace app\middleware;

use think\Request;
use think\Response;

/**
 * CORS middleware for the decoupled frontend.
 *
 * Reads allowed origins from the CORS_ALLOWED_ORIGINS environment variable
 * (comma-separated list, default '*') and attaches the appropriate headers
 * to every response.  Preflight OPTIONS requests are short-circuited with a
 * 204 No Content response.
 */
class CorsMiddleware
{
    /**
     * @param Request  $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $allowedOriginsRaw = env('CORS_ALLOWED_ORIGINS', '*');
        $allowedOrigins    = array_map('trim', explode(',', (string) $allowedOriginsRaw));
        $requestOrigin     = $request->header('Origin', '');

        // Determine the value for the Access-Control-Allow-Origin header.
        // When credentials are enabled, browsers reject wildcard origins.
        // If configured as '*', reflect the request origin instead.
        if (in_array('*', $allowedOrigins, true)) {
            $origin = $requestOrigin !== '' ? $requestOrigin : '*';
        } elseif (in_array($requestOrigin, $allowedOrigins, true)) {
            $origin = $requestOrigin;
        } else {
            $origin = '';
        }

        // Handle preflight OPTIONS requests immediately.
        if ($request->isOptions()) {
            return Response::create('', 'html', 204)
                ->header([
                    'Access-Control-Allow-Origin'      => $origin,
                    'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Max-Age'           => '86400',
                ]);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($origin !== '') {
            $response->header([
                'Access-Control-Allow-Origin'      => $origin,
                'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age'           => '86400',
            ]);
        }

        return $response;
    }
}
