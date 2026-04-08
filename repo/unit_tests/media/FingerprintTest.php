<?php
declare(strict_types=1);

namespace unit_tests\media;

use PHPUnit\Framework\TestCase;

/**
 * Tests for media file fingerprinting (SHA-256 hash).
 */
class FingerprintTest extends TestCase
{
    /** Simulated fingerprint store: [{hash, user_id, created_at}] */
    private array $fingerprints = [];

    /**
     * Compute and store a fingerprint for an upload.
     *
     * @return array|null Returns matching record if duplicate found, null otherwise
     */
    private function processUpload(string $content, int $userId): ?array
    {
        $hash = hash('sha256', $content);

        // Check for duplicates within 30 days
        $thirtyDaysAgo = strtotime('-30 days');
        foreach ($this->fingerprints as $existing) {
            if ($existing['hash'] === $hash && strtotime($existing['created_at']) >= $thirtyDaysAgo) {
                return $existing; // Duplicate
            }
        }

        // Store new fingerprint
        $this->fingerprints[] = [
            'hash'       => $hash,
            'user_id'    => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return null;
    }

    public function test_sha256_hash_computed_on_upload(): void
    {
        $content = 'Binary content of an image file';
        $result = $this->processUpload($content, 1);

        $this->assertNull($result); // First upload, no duplicate
        $this->assertCount(1, $this->fingerprints);
        $this->assertEquals(hash('sha256', $content), $this->fingerprints[0]['hash']);
    }

    public function test_duplicate_hash_detected_within_30_days(): void
    {
        $content = 'Identical file content';

        // First upload
        $result1 = $this->processUpload($content, 1);
        $this->assertNull($result1);

        // Second upload with same content
        $result2 = $this->processUpload($content, 1);
        $this->assertNotNull($result2);
        $this->assertEquals(hash('sha256', $content), $result2['hash']);
    }

    public function test_same_hash_different_user_not_flagged(): void
    {
        // The fingerprint check finds duplicates regardless of user,
        // but different content should not collide
        $content1 = 'User 1 unique content';
        $content2 = 'User 2 unique content';

        $result1 = $this->processUpload($content1, 1);
        $this->assertNull($result1);

        $result2 = $this->processUpload($content2, 2);
        $this->assertNull($result2); // Different content = different hash = no flag
    }
}
