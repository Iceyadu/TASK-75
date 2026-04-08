<?php
declare(strict_types=1);

namespace unit_tests\review;

use PHPUnit\Framework\TestCase;

/**
 * Tests for duplicate detection using trigram Jaccard similarity and file hash.
 */
class DuplicateDetectionTest extends TestCase
{
    /**
     * Compute character-level trigrams from a string.
     */
    private function trigrams(string $text): array
    {
        $text = mb_strtolower(trim($text));
        $trigrams = [];
        $len = mb_strlen($text);

        for ($i = 0; $i <= $len - 3; $i++) {
            $trigrams[] = mb_substr($text, $i, 3);
        }

        return array_unique($trigrams);
    }

    /**
     * Compute Jaccard similarity coefficient between two sets of trigrams.
     */
    private function trigramJaccard(string $a, string $b): float
    {
        $triA = $this->trigrams($a);
        $triB = $this->trigrams($b);

        if (empty($triA) && empty($triB)) {
            return 1.0;
        }

        $intersection = count(array_intersect($triA, $triB));
        $union = count(array_unique(array_merge($triA, $triB)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Simulated file hash store: hash => [user_id, created_at].
     */
    private array $fileHashes = [];

    private function checkFileHash(string $content, int $userId): ?array
    {
        $hash = hash('sha256', $content);

        foreach ($this->fileHashes as $existing) {
            if ($existing['hash'] === $hash) {
                $age = time() - strtotime($existing['created_at']);
                if ($age <= 30 * 86400) { // Within 30 days
                    return $existing; // Duplicate found
                }
            }
        }

        // Store new hash
        $this->fileHashes[] = [
            'hash'       => $hash,
            'user_id'    => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return null;
    }

    public function test_trigram_jaccard_identical_strings_returns_1(): void
    {
        $score = $this->trigramJaccard('hello world', 'hello world');
        $this->assertEquals(1.0, $score);
    }

    public function test_trigram_jaccard_different_strings_returns_low(): void
    {
        $score = $this->trigramJaccard('good morning sunshine', 'xyztuvwabcde');
        $this->assertLessThan(0.3, $score);
    }

    public function test_trigram_jaccard_similar_strings_above_threshold(): void
    {
        $score = $this->trigramJaccard(
            'This ride was excellent, very comfortable',
            'This ride was excellent, very comfortable and safe'
        );
        $this->assertGreaterThan(0.6, $score);
    }

    public function test_file_hash_duplicate_detected(): void
    {
        $content = 'This is the file content for a photo upload';

        // First upload -- no duplicate
        $result1 = $this->checkFileHash($content, 1);
        $this->assertNull($result1);

        // Second upload with same content -- duplicate detected
        $result2 = $this->checkFileHash($content, 1);
        $this->assertNotNull($result2);
        $this->assertEquals(hash('sha256', $content), $result2['hash']);
    }

    public function test_file_hash_different_files_not_flagged(): void
    {
        $content1 = 'File content version A';
        $content2 = 'Completely different file content B';

        $result1 = $this->checkFileHash($content1, 1);
        $this->assertNull($result1);

        $result2 = $this->checkFileHash($content2, 1);
        $this->assertNull($result2);
    }
}
