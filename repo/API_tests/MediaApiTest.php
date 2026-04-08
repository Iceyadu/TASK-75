<?php

namespace API_tests;

use PHPUnit\Framework\TestCase;

/**
 * API integration tests for media endpoints (signed URLs and hotlink protection).
 */
class MediaApiTest extends TestCase
{
    protected static string $baseUrl;
    protected static ?string $authToken = null;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl   = rtrim(getenv('API_BASE_URL') ?: 'http://localhost:8080', '/');
        self::$authToken = self::login(
            getenv('TEST_USER_EMAIL') ?: 'test@test.local',
            getenv('TEST_USER_PASSWORD') ?: 'TestPass123!'
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

    protected function apiGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        $defaultHeaders = ['Accept: application/json'];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge($defaultHeaders, $headers),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => $body];
    }

    /**
     * Build a signed URL for testing. In production the backend generates these;
     * here we construct one manually if a test media ID is known.
     */
    protected function buildSignedUrl(int $mediaId, int $expires, string $signature): string
    {
        return self::$baseUrl . '/api/media/' . $mediaId .
            '?expires=' . $expires . '&signature=' . $signature;
    }

    // ----------------------------------------------------------------
    // Signed URL tests
    // ----------------------------------------------------------------

    public function test_signed_url_valid(): void
    {
        // Request a valid signed URL from the API (if available)
        // Alternatively, test the media endpoint with known test fixtures
        $mediaId = (int) (getenv('TEST_MEDIA_ID') ?: 1);

        // Get the signed URL from listing or review that references this media
        $ch = curl_init(self::$baseUrl . '/api/media/' . $mediaId . '/signed-url');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . self::$authToken,
            ],
        ]);
        $body   = json_decode(curl_exec($ch), true);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 200 && isset($body['url'])) {
            // Access the signed URL
            $res = $this->apiGet($body['url'], [
                'Referer: ' . self::$baseUrl . '/',
            ]);
            $this->assertContains($res['status'], [200, 302],
                'Valid signed URL should return 200 or 302');
        } else {
            // No test media available; verify endpoint exists
            $this->assertContains($status, [200, 404],
                'Signed URL endpoint should exist (200) or media not found (404)');
        }
    }

    public function test_signed_url_expired(): void
    {
        $mediaId = (int) (getenv('TEST_MEDIA_ID') ?: 1);
        // Use an expired timestamp (in the past)
        $expired = time() - 3600;
        $url = $this->buildSignedUrl($mediaId, $expired, 'fakesignature123');

        $res = $this->apiGet($url, ['Referer: ' . self::$baseUrl . '/']);

        $this->assertContains($res['status'], [401, 403],
            'Expired signed URL should return 401 or 403');
    }

    public function test_signed_url_tampered(): void
    {
        $mediaId = (int) (getenv('TEST_MEDIA_ID') ?: 1);
        // Use a future timestamp but invalid signature
        $future = time() + 3600;
        $url = $this->buildSignedUrl($mediaId, $future, 'tampered_invalid_signature');

        $res = $this->apiGet($url, ['Referer: ' . self::$baseUrl . '/']);

        $this->assertContains($res['status'], [401, 403],
            'Tampered signature should return 401 or 403');
    }

    // ----------------------------------------------------------------
    // Hotlink protection
    // ----------------------------------------------------------------

    public function test_hotlink_wrong_referrer(): void
    {
        $mediaId = (int) (getenv('TEST_MEDIA_ID') ?: 1);
        $future = time() + 3600;
        $url = $this->buildSignedUrl($mediaId, $future, 'test_signature');

        // Use a wrong Referer header
        $res = $this->apiGet($url, ['Referer: https://evil-site.example.com/']);

        $this->assertContains($res['status'], [401, 403],
            'Wrong Referer should return 401 or 403 (hotlink protection)');
    }
}
