<?php
declare(strict_types=1);

namespace app\service;

use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\model\ApiToken;
use think\Collection;

class TokenService
{
    /**
     * Create a new API token for a user.
     */
    public function create(int $userId, string $name, int $expiresInDays = 90): array
    {
        $plaintext = generate_token();
        $tokenHash = hash('sha256', $plaintext);

        $token = new ApiToken();
        $token->user_id    = $userId;
        $token->name       = $name;
        $token->token_hash = $tokenHash;
        $token->expires_at = date('Y-m-d\TH:i:s\Z', strtotime("+{$expiresInDays} days"));
        $token->save();

        return [
            'token'           => $token,
            'plaintext_token' => $plaintext,
        ];
    }

    /**
     * List all active (non-revoked) tokens for a user.
     */
    public function listForUser(int $userId): Collection
    {
        return ApiToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->order('created_at', 'desc')
            ->select();
    }

    /**
     * Revoke a token, verifying ownership.
     */
    public function revoke(int $tokenId, int $userId): void
    {
        $token = ApiToken::find($tokenId);
        if (!$token) {
            throw new NotFoundException('Token not found', 40401);
        }
        if ((int) $token->user_id !== $userId) {
            throw new ForbiddenException('You do not own this token');
        }
        $token->revoked_at = date('Y-m-d\TH:i:s\Z');
        $token->save();
    }

    /**
     * Rotate a token: revoke old, create new with same name and remaining expiry.
     */
    public function rotate(int $tokenId, int $userId): array
    {
        $oldToken = ApiToken::find($tokenId);
        if (!$oldToken) {
            throw new NotFoundException('Token not found', 40401);
        }
        if ((int) $oldToken->user_id !== $userId) {
            throw new ForbiddenException('You do not own this token');
        }

        // Calculate remaining days
        $expiresTimestamp = strtotime($oldToken->expires_at);
        $remainingSeconds = max($expiresTimestamp - time(), 86400); // at least 1 day
        $remainingDays = (int) ceil($remainingSeconds / 86400);

        // Revoke old
        $oldToken->revoked_at = date('Y-m-d\TH:i:s\Z');
        $oldToken->save();

        // Create new with same name and remaining expiry
        return $this->create($userId, $oldToken->name, $remainingDays);
    }
}
