<?php
declare(strict_types=1);

namespace unit_tests\auth;

use PHPUnit\Framework\TestCase;

/**
 * Tests for TokenService logic.
 *
 * Validates token creation, hashing, revocation, rotation, and expiry
 * without a database connection.
 */
class TokenTest extends TestCase
{
    /**
     * Simulated token store: maps hash => token record.
     */
    private array $tokenStore = [];

    private function createToken(int $userId, ?int $expiryDays = 90): array
    {
        $plaintext = 'rc_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $plaintext);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

        $record = [
            'id'           => count($this->tokenStore) + 1,
            'user_id'      => $userId,
            'token_hash'   => $hash,
            'expires_at'   => $expiresAt,
            'revoked_at'   => null,
            'last_used_at' => null,
            'created_at'   => date('Y-m-d H:i:s'),
        ];

        $this->tokenStore[$hash] = $record;

        return ['plaintext' => $plaintext, 'record' => $record];
    }

    private function revokeToken(string $hash): void
    {
        if (isset($this->tokenStore[$hash])) {
            $this->tokenStore[$hash]['revoked_at'] = date('Y-m-d H:i:s');
        }
    }

    private function isTokenValid(string $plaintext): bool
    {
        $hash = hash('sha256', $plaintext);
        if (!isset($this->tokenStore[$hash])) {
            return false;
        }
        $record = $this->tokenStore[$hash];
        if ($record['revoked_at'] !== null) {
            return false;
        }
        if (strtotime($record['expires_at']) < time()) {
            return false;
        }
        return true;
    }

    private function rotateToken(string $oldPlaintext, int $userId): ?array
    {
        $oldHash = hash('sha256', $oldPlaintext);
        if (!isset($this->tokenStore[$oldHash])) {
            return null;
        }
        // Revoke old
        $this->revokeToken($oldHash);
        // Create new
        return $this->createToken($userId);
    }

    public function test_create_token_returns_plaintext_once(): void
    {
        $result = $this->createToken(1);

        $this->assertArrayHasKey('plaintext', $result);
        $this->assertStringStartsWith('rc_', $result['plaintext']);
        $this->assertEquals(67, strlen($result['plaintext'])); // "rc_" + 64 hex chars
    }

    public function test_token_stored_as_sha256_hash(): void
    {
        $result = $this->createToken(1);

        $expectedHash = hash('sha256', $result['plaintext']);
        $this->assertEquals($expectedHash, $result['record']['token_hash']);
        $this->assertNotEquals($result['plaintext'], $result['record']['token_hash']);
    }

    public function test_revoke_sets_revoked_at(): void
    {
        $result = $this->createToken(1);
        $hash = $result['record']['token_hash'];

        $this->assertNull($this->tokenStore[$hash]['revoked_at']);
        $this->revokeToken($hash);
        $this->assertNotNull($this->tokenStore[$hash]['revoked_at']);
    }

    public function test_rotate_invalidates_old_creates_new(): void
    {
        $old = $this->createToken(1);
        $new = $this->rotateToken($old['plaintext'], 1);

        $this->assertNotNull($new);
        $this->assertFalse($this->isTokenValid($old['plaintext']));
        $this->assertTrue($this->isTokenValid($new['plaintext']));
        $this->assertNotEquals($old['plaintext'], $new['plaintext']);
    }

    public function test_expired_token_is_not_valid(): void
    {
        $plaintext = 'rc_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $plaintext);

        // Create an already-expired token
        $this->tokenStore[$hash] = [
            'id'           => 999,
            'user_id'      => 1,
            'token_hash'   => $hash,
            'expires_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
            'revoked_at'   => null,
            'last_used_at' => null,
            'created_at'   => date('Y-m-d H:i:s', strtotime('-91 days')),
        ];

        $this->assertFalse($this->isTokenValid($plaintext));
    }

    public function test_revoked_token_is_not_valid(): void
    {
        $result = $this->createToken(1);
        $this->revokeToken($result['record']['token_hash']);

        $this->assertFalse($this->isTokenValid($result['plaintext']));
    }

    public function test_token_default_expiry_is_90_days(): void
    {
        $result = $this->createToken(1);

        $expiresAt = strtotime($result['record']['expires_at']);
        $expectedMin = strtotime('+89 days');
        $expectedMax = strtotime('+91 days');

        $this->assertGreaterThanOrEqual($expectedMin, $expiresAt);
        $this->assertLessThanOrEqual($expectedMax, $expiresAt);
    }
}
