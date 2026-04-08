<?php

namespace API_tests;

use PHPUnit\Framework\TestCase;

/**
 * API integration tests for permission enforcement.
 * Verifies that endpoints enforce RBAC correctly for different user roles.
 */
class PermissionApiTest extends TestCase
{
    protected static string $baseUrl;
    protected static ?string $userToken      = null;
    protected static ?string $moderatorToken = null;
    protected static ?string $adminToken     = null;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = rtrim(getenv('API_BASE_URL') ?: 'http://localhost:8080', '/');

        self::$userToken = self::login(
            getenv('TEST_USER_EMAIL') ?: 'test@test.local',
            getenv('TEST_USER_PASSWORD') ?: 'TestPass123!'
        );
        self::$moderatorToken = self::login(
            getenv('TEST_MODERATOR_EMAIL') ?: 'moderator@test.local',
            getenv('TEST_MODERATOR_PASSWORD') ?: 'TestPass123!'
        );
        self::$adminToken = self::login(
            getenv('TEST_ADMIN_EMAIL') ?: 'admin@test.local',
            getenv('TEST_ADMIN_PASSWORD') ?: 'TestPass123!'
        );
    }

    protected static function login(string $email, string $password): string
    {
        $ch = curl_init(self::$baseUrl . '/api/auth/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(['email' => $email, 'password' => $password]),
        ]);
        $body = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $body['token'] ?? '';
    }

    protected function apiGet(string $path, ?string $token = null): int
    {
        $ch = curl_init(self::$baseUrl . $path);
        $headers = ['Accept: application/json'];
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $status;
    }

    // ----------------------------------------------------------------
    // Regular user restrictions
    // ----------------------------------------------------------------

    public function test_regular_user_cannot_access_moderation(): void
    {
        $status = $this->apiGet('/api/moderation/queue', self::$userToken);
        $this->assertEquals(403, $status,
            'Regular user should get 403 on moderation queue');
    }

    public function test_regular_user_cannot_access_governance(): void
    {
        $status = $this->apiGet('/api/governance/quality-metrics', self::$userToken);
        $this->assertEquals(403, $status,
            'Regular user should get 403 on governance metrics');
    }

    public function test_regular_user_cannot_access_audit(): void
    {
        $status = $this->apiGet('/api/audit/logs', self::$userToken);
        $this->assertEquals(403, $status,
            'Regular user should get 403 on audit logs');
    }

    public function test_regular_user_cannot_access_admin_users(): void
    {
        $status = $this->apiGet('/api/users', self::$userToken);
        $this->assertEquals(403, $status,
            'Regular user should get 403 on user management');
    }

    // ----------------------------------------------------------------
    // Moderator access
    // ----------------------------------------------------------------

    public function test_moderator_can_access_moderation(): void
    {
        $status = $this->apiGet('/api/moderation/queue', self::$moderatorToken);
        $this->assertEquals(200, $status,
            'Moderator should get 200 on moderation queue');
    }

    // ----------------------------------------------------------------
    // Admin access
    // ----------------------------------------------------------------

    public function test_admin_can_access_all(): void
    {
        $endpoints = [
            '/api/moderation/queue',
            '/api/governance/quality-metrics',
            '/api/governance/lineage',
            '/api/audit/logs',
            '/api/users',
            '/api/org/settings',
        ];

        foreach ($endpoints as $ep) {
            $status = $this->apiGet($ep, self::$adminToken);
            $this->assertEquals(200, $status,
                "Admin should get 200 on $ep");
        }
    }
}
