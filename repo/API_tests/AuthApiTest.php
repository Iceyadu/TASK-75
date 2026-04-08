<?php

namespace API_tests;

use PHPUnit\Framework\TestCase;

/**
 * API integration tests for authentication endpoints.
 * Requires a running backend + MySQL with seeded test data.
 *
 * Base URL is configured via the API_BASE_URL environment variable.
 */
class AuthApiTest extends TestCase
{
    protected static string $baseUrl;
    protected static string $testEmail;
    protected static string $testPassword;
    protected static string $testOrgCode;
    protected static ?string $authToken = null;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl     = rtrim(getenv('API_BASE_URL') ?: 'http://localhost:8080', '/');
        self::$testEmail   = 'authtest_' . uniqid() . '@test.local';
        self::$testPassword = 'TestPass123!';
        self::$testOrgCode = getenv('TEST_ORG_CODE') ?: 'TESTORG';
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    protected function apiRequest(string $method, string $path, array $data = [], ?string $token = null): array
    {
        $url = self::$baseUrl . $path;
        $ch  = curl_init();

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET') {
            if ($data) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body'   => json_decode($body, true) ?? [],
        ];
    }

    // ----------------------------------------------------------------
    // Registration
    // ----------------------------------------------------------------

    public function test_register_success(): void
    {
        $res = $this->apiRequest('POST', '/api/auth/register', [
            'name'     => 'Auth Test User',
            'email'    => self::$testEmail,
            'password' => self::$testPassword,
            'password_confirmation' => self::$testPassword,
            'org_code' => self::$testOrgCode,
        ]);

        $this->assertEquals(201, $res['status'], 'Register should return 201');
        $this->assertArrayHasKey('user', $res['body']);
        $this->assertEquals(self::$testEmail, $res['body']['user']['email']);
    }

    public function test_register_duplicate_email(): void
    {
        // First registration already done in previous test; register again with same email
        $res = $this->apiRequest('POST', '/api/auth/register', [
            'name'     => 'Duplicate User',
            'email'    => self::$testEmail,
            'password' => self::$testPassword,
            'password_confirmation' => self::$testPassword,
            'org_code' => self::$testOrgCode,
        ]);

        $this->assertEquals(422, $res['status'], 'Duplicate email should return 422');
        $this->assertArrayHasKey('errors', $res['body']);
    }

    public function test_register_invalid_org_code(): void
    {
        $res = $this->apiRequest('POST', '/api/auth/register', [
            'name'     => 'Bad Org User',
            'email'    => 'badorg_' . uniqid() . '@test.local',
            'password' => self::$testPassword,
            'password_confirmation' => self::$testPassword,
            'org_code' => 'INVALID_CODE_XYZ',
        ]);

        $this->assertContains($res['status'], [400, 422], 'Invalid org code should return 400 or 422');
    }

    // ----------------------------------------------------------------
    // Login
    // ----------------------------------------------------------------

    public function test_login_success(): void
    {
        $res = $this->apiRequest('POST', '/api/auth/login', [
            'email'    => self::$testEmail,
            'password' => self::$testPassword,
        ]);

        $this->assertEquals(200, $res['status'], 'Login should return 200');
        $this->assertArrayHasKey('token', $res['body'], 'Response should contain token');
        $this->assertNotEmpty($res['body']['token'], 'Token should not be empty');

        // Store token for subsequent tests
        self::$authToken = $res['body']['token'];
    }

    public function test_login_invalid_credentials(): void
    {
        $res = $this->apiRequest('POST', '/api/auth/login', [
            'email'    => self::$testEmail,
            'password' => 'WrongPassword999!',
        ]);

        $this->assertEquals(401, $res['status'], 'Invalid credentials should return 401');
    }

    // ----------------------------------------------------------------
    // Authenticated endpoints
    // ----------------------------------------------------------------

    public function test_me_returns_user_with_roles(): void
    {
        $this->assertNotNull(self::$authToken, 'Auth token must be set from login test');

        $res = $this->apiRequest('GET', '/api/auth/me', [], self::$authToken);

        $this->assertEquals(200, $res['status'], '/me should return 200');
        $this->assertArrayHasKey('user', $res['body']);
        $this->assertEquals(self::$testEmail, $res['body']['user']['email']);
        $this->assertArrayHasKey('roles', $res['body']['user'], 'User should have roles array');
        $this->assertIsArray($res['body']['user']['roles']);
    }

    public function test_me_requires_auth(): void
    {
        $res = $this->apiRequest('GET', '/api/auth/me');

        $this->assertEquals(401, $res['status'], '/me without token should return 401');
    }

    // ----------------------------------------------------------------
    // Logout
    // ----------------------------------------------------------------

    public function test_logout_clears_session(): void
    {
        $this->assertNotNull(self::$authToken, 'Auth token must be set from login test');

        $res = $this->apiRequest('POST', '/api/auth/logout', [], self::$authToken);
        $this->assertContains($res['status'], [200, 204], 'Logout should return 200 or 204');

        // Verify token is invalidated
        $meRes = $this->apiRequest('GET', '/api/auth/me', [], self::$authToken);
        $this->assertEquals(401, $meRes['status'], 'Token should be invalid after logout');
    }
}
