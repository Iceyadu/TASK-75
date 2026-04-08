<?php
declare(strict_types=1);

namespace app\middleware;

use app\exception\AuthException;
use app\model\ApiToken;
use app\model\User;
use think\Request;
use think\Response;
use think\facade\Session;

/**
 * Authentication middleware.
 *
 * Supports two authentication strategies evaluated in order:
 *  1. Bearer token – SHA-256 hash lookup against the api_tokens table.
 *  2. Session – standard ThinkPHP session containing a user_id.
 *
 * On success the middleware sets:
 *  - $request->user      (app\model\User instance)
 *  - $request->orgId     (int – the user's organization_id)
 *  - $request->tokenAuth (bool – true when authenticated via Bearer token)
 */
class AuthMiddleware
{
    /**
     * @param Request  $request
     * @param \Closure $next
     * @return Response
     * @throws AuthException
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $user      = null;
        $tokenAuth = false;

        // ── Strategy 1: Bearer token ────────────────────────────────────
        $authHeader = $request->header('Authorization', '');
        if (stripos($authHeader, 'Bearer ') === 0) {
            $rawToken  = trim(substr($authHeader, 7));
            $tokenHash = hash('sha256', $rawToken);

            /** @var ApiToken|null $apiToken */
            $apiToken = ApiToken::where('token_hash', $tokenHash)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->find();

            if ($apiToken && $apiToken->isValid()) {
                $user      = $apiToken->user;
                $tokenAuth = true;

                // Touch last_used_at without triggering model events.
                $apiToken->last_used_at = date('Y-m-d H:i:s');
                $apiToken->save();
            }
        }

        // ── Strategy 2: Session ─────────────────────────────────────────
        if ($user === null) {
            $userId = Session::get('user_id');
            if ($userId) {
                $user = User::with(['roles'])->find($userId);
            }
        }

        // ── No valid credentials ────────────────────────────────────────
        if ($user === null) {
            throw new AuthException('Authentication required', 40101);
        }

        // ── Account status check ────────────────────────────────────────
        if ($user->status !== 'active') {
            throw new AuthException('Account disabled', 40102);
        }

        // ── Populate request context ────────────────────────────────────
        $request->user      = $user;
        $request->orgId     = (int) $user->organization_id;
        $request->tokenAuth = $tokenAuth;

        return $next($request);
    }
}
