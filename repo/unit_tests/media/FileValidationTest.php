<?php
declare(strict_types=1);

namespace unit_tests\media;

use PHPUnit\Framework\TestCase;

/**
 * Tests for file upload validation rules.
 *
 * Rules:
 *   - Photos: max 5 MB
 *   - Videos: max 50 MB
 *   - Max 5 files per review
 *   - Only allowed MIME types
 */
class FileValidationTest extends TestCase
{
    private const PHOTO_MAX_BYTES = 5 * 1024 * 1024;     // 5 MB
    private const VIDEO_MAX_BYTES = 50 * 1024 * 1024;    // 50 MB
    private const MAX_FILES_PER_REVIEW = 5;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/quicktime',
        'video/webm',
    ];

    /**
     * Validate a single file upload.
     *
     * @throws \RuntimeException on validation failure
     */
    private function validateFile(string $mimeType, int $sizeBytes): void
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException("Invalid MIME type: {$mimeType}", 40001);
        }

        $isVideo = strpos($mimeType, 'video/') === 0;
        $maxSize = $isVideo ? self::VIDEO_MAX_BYTES : self::PHOTO_MAX_BYTES;
        $typeLabel = $isVideo ? 'video' : 'photo';

        if ($sizeBytes > $maxSize) {
            $maxMb = $maxSize / 1024 / 1024;
            throw new \RuntimeException(
                "File exceeds maximum {$typeLabel} size of {$maxMb} MB",
                40001
            );
        }
    }

    /**
     * Validate file count per review.
     *
     * @throws \RuntimeException if too many files
     */
    private function validateFileCount(int $currentCount, int $newFiles): void
    {
        if ($currentCount + $newFiles > self::MAX_FILES_PER_REVIEW) {
            throw new \RuntimeException(
                'Maximum ' . self::MAX_FILES_PER_REVIEW . ' files per review',
                40001
            );
        }
    }

    public function test_photo_under_5mb_accepted(): void
    {
        $this->validateFile('image/jpeg', 3 * 1024 * 1024); // 3 MB
        $this->assertTrue(true); // No exception means success
    }

    public function test_photo_over_5mb_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('photo size');
        $this->validateFile('image/jpeg', 6 * 1024 * 1024); // 6 MB
    }

    public function test_video_under_50mb_accepted(): void
    {
        $this->validateFile('video/mp4', 30 * 1024 * 1024); // 30 MB
        $this->assertTrue(true);
    }

    public function test_video_over_50mb_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('video size');
        $this->validateFile('video/mp4', 55 * 1024 * 1024); // 55 MB
    }

    public function test_max_5_files_per_review(): void
    {
        // Already have 4 files, adding 1 more is OK
        $this->validateFileCount(4, 1);
        $this->assertTrue(true);

        // Already have 4 files, adding 2 exceeds limit
        $this->expectException(\RuntimeException::class);
        $this->validateFileCount(4, 2);
    }

    public function test_invalid_mime_type_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid MIME type');
        $this->validateFile('application/pdf', 1024);
    }
}
