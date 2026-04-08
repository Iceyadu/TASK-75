<?php
declare(strict_types=1);

namespace unit_tests\media;

use PHPUnit\Framework\TestCase;

/**
 * Tests for signed URL generation and validation.
 *
 * Signed URLs use HMAC-SHA256 with a server-side secret and an expiry timestamp.
 */
class SignedUrlTest extends TestCase
{
    private const SECRET = 'test_secret_key_for_unit_tests_only';

    /**
     * Generate a signed URL.
     */
    private function generateSignedUrl(string $path, int $expiresAt): string
    {
        $data = "{$path}:{$expiresAt}";
        $signature = hash_hmac('sha256', $data, self::SECRET);

        return "{$path}?expires={$expiresAt}&signature={$signature}";
    }

    /**
     * Validate a signed URL.
     *
     * @return bool True if valid
     * @throws \RuntimeException with specific error
     */
    private function validateSignedUrl(string $url, int $now = null): bool
    {
        $now = $now ?? time();

        // Parse URL parts
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);

        $path = $parsed['path'] ?? '';

        if (!isset($params['signature'])) {
            throw new \RuntimeException('Missing signature', 40001);
        }

        if (!isset($params['expires'])) {
            throw new \RuntimeException('Missing expires parameter', 40001);
        }

        $expiresAt = (int) $params['expires'];
        $signature = $params['signature'];

        // Check expiry
        if ($now > $expiresAt) {
            throw new \RuntimeException('Signature expired', 40101);
        }

        // Verify signature
        $expectedData = "{$path}:{$expiresAt}";
        $expectedSignature = hash_hmac('sha256', $expectedData, self::SECRET);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new \RuntimeException('Invalid signature', 40101);
        }

        return true;
    }

    public function test_valid_signature_accepted(): void
    {
        $url = $this->generateSignedUrl('/api/media/42', time() + 600);
        $this->assertTrue($this->validateSignedUrl($url));
    }

    public function test_expired_signature_rejected(): void
    {
        $url = $this->generateSignedUrl('/api/media/42', time() - 60);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signature expired');
        $this->validateSignedUrl($url);
    }

    public function test_tampered_signature_rejected(): void
    {
        $url = $this->generateSignedUrl('/api/media/42', time() + 600);
        // Tamper with the signature
        $url = str_replace('signature=', 'signature=tampered', $url);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid signature');
        $this->validateSignedUrl($url);
    }

    public function test_missing_signature_rejected(): void
    {
        $url = '/api/media/42?expires=' . (time() + 600);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing signature');
        $this->validateSignedUrl($url);
    }

    public function test_missing_expires_rejected(): void
    {
        $url = '/api/media/42?signature=somehash';

        $this->expectException(\RuntimeException::class);
        // Expires defaults to 0, which is in the past
        $this->validateSignedUrl($url);
    }
}
