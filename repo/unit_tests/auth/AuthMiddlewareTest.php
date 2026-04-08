<?php
declare(strict_types=1);

namespace unit_tests\auth;

use PHPUnit\Framework\TestCase;

/**
 * Tests for AuthMiddleware decision logic.
 *
 * Simulates the middleware's authentication strategies without real
 * ThinkPHP Request/Response objects.
 */
class AuthMiddlewareTest extends TestCase
{
    /** Simulated token store: hash => record */
    private array $tokens = [];

    /** Simulated user store: id => record */
    private array $users = [];

    /** Simulated session store: key => value */
    private array $session = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Set up a valid active user
        $this->users[1] = [
            'id'              => 1,
            'email'           => 'alice@example.local',
            'status'          => 'active',
            'organization_id' => 1,
        ];

        // Set up a disabled user
        $this->users[2] = [
            'id'              => 2,
            'email'           => 'disabled@example.local',
            'status'          => 'disabled',
            'organization_id' => 1,
        ];

        // Create a valid token for user 1
        $plaintext = 'rc_validtoken123456789012345678901234567890123456789012345678901';
        $hash = hash('sha256', $plaintext);
        $this->tokens[$hash] = [
            'user_id'    => 1,
            'token_hash' => $hash,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'revoked_at' => null,
        ];

        // Create an expired token
        $expiredPlain = 'rc_expiredtoken12345678901234567890123456789012345678901234567';
        $expiredHash = hash('sha256', $expiredPlain);
        $this->tokens[$expiredHash] = [
            'user_id'    => 1,
            'token_hash' => $expiredHash,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'revoked_at' => null,
        ];

        // Create a revoked token
        $revokedPlain = 'rc_revokedtoken1234567890123456789012345678901234567890123456';
        $revokedHash = hash('sha256', $revokedPlain);
        $this->tokens[$revokedHash] = [
            'user_id'    => 1,
            'token_hash' => $revokedHash,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'revoked_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Simulates the AuthMiddleware logic.
     *
     * @return array{user: array, tokenAuth: bool}
     * @throws \RuntimeException on authentication failure
     */
    private function authenticate(?string $authHeader, ?int $sessionUserId): array
    {
        $user = null;
        $tokenAuth = false;

        // Strategy 1: Bearer token
        if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
            $rawToken = trim(substr($authHeader, 7));
            $tokenHash = hash('sha256', $rawToken);

            if (isset($this->tokens[$tokenHash])) {
                $record = $this->tokens[$tokenHash];
                if ($record['revoked_at'] === null
                    && strtotime($record['expires_at']) > time()
                ) {
                    $user = $this->users[$record['user_id']] ?? null;
                    $tokenAuth = true;
                }
            }
        }

        // Strategy 2: Session
        if ($user === null && $sessionUserId !== null) {
            $user = $this->users[$sessionUserId] ?? null;
        }

        if ($user === null) {
            throw new \RuntimeException('Authentication required', 40101);
        }

        if ($user['status'] !== 'active') {
            throw new \RuntimeException('Account disabled', 40102);
        }

        return ['user' => $user, 'tokenAuth' => $tokenAuth];
    }

    public function test_bearer_token_authenticates_user(): void
    {
        $plaintext = 'rc_validtoken123456789012345678901234567890123456789012345678901';
        $result = $this->authenticate("Bearer {$plaintext}", null);

        $this->assertEquals(1, $result['user']['id']);
        $this->assertTrue($result['tokenAuth']);
    }

    public function test_expired_bearer_token_returns_401(): void
    {
        $plaintext = 'rc_expiredtoken12345678901234567890123456789012345678901234567';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40101);
        $this->authenticate("Bearer {$plaintext}", null);
    }

    public function test_revoked_bearer_token_returns_401(): void
    {
        $plaintext = 'rc_revokedtoken1234567890123456789012345678901234567890123456';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40101);
        $this->authenticate("Bearer {$plaintext}", null);
    }

    public function test_session_authenticates_user(): void
    {
        $result = $this->authenticate(null, 1);

        $this->assertEquals(1, $result['user']['id']);
        $this->assertFalse($result['tokenAuth']);
    }

    public function test_no_credentials_returns_401(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40101);
        $this->authenticate(null, null);
    }

    public function test_disabled_user_returns_401(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40102);
        $this->authenticate(null, 2);
    }
}
