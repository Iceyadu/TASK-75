<?php
declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\model\Media;
use think\facade\Filesystem;

class MediaService
{
    /**
     * Allowed MIME types and size limits.
     */
    private const PHOTO_MIMES    = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const VIDEO_MIMES    = ['video/mp4', 'video/webm'];
    private const PHOTO_MAX_SIZE = 5 * 1024 * 1024;    // 5MB
    private const VIDEO_MAX_SIZE = 50 * 1024 * 1024;   // 50MB
    private const MAX_FILES_PER_PARENT = 5;

    /**
     * Upload a file, validate, store, and create a Media record.
     */
    public function upload(int $orgId, int $userId, $file, string $parentType, int $parentId): Media
    {
        // Get file info
        $mimeType = $file->getMime();
        $fileSize = $file->getSize();
        $ext      = strtolower($file->extension());

        // Determine media type and validate
        $isPhoto = in_array($mimeType, self::PHOTO_MIMES, true);
        $isVideo = in_array($mimeType, self::VIDEO_MIMES, true);

        if (!$isPhoto && !$isVideo) {
            throw new ValidationException('Unsupported file type', [
                'file' => 'Allowed types: jpeg, png, gif, webp, mp4, webm',
            ]);
        }

        if ($isPhoto && $fileSize > self::PHOTO_MAX_SIZE) {
            throw new ValidationException('File too large', [
                'file' => 'Photos must be under 5MB',
            ]);
        }

        if ($isVideo && $fileSize > self::VIDEO_MAX_SIZE) {
            throw new ValidationException('File too large', [
                'file' => 'Videos must be under 50MB',
            ]);
        }

        // Check max files per parent
        $existingCount = Media::where('parent_type', $parentType)
            ->where('parent_id', $parentId)
            ->count();

        if ($existingCount >= self::MAX_FILES_PER_PARENT) {
            throw new BusinessException(
                'Maximum of ' . self::MAX_FILES_PER_PARENT . ' files per item',
                40901,
                409
            );
        }

        // Generate UUID filename
        $uuid     = $this->generateUuid();
        $filename = $uuid . '.' . $ext;

        // Build storage path
        $datePath   = date('Y/m/d');
        $storagePath = "{$orgId}/{$datePath}";
        $fullPath    = $storagePath . '/' . $filename;

        // Compute SHA-256 hash
        $tempPath = $file->getPathname();
        $fileHash = hash_file('sha256', $tempPath);

        // Check for duplicate hash (same user, 30 days)
        $thirtyDaysAgo = date('Y-m-d\TH:i:s\Z', strtotime('-30 days'));
        $duplicate = Media::where('user_id', $userId)
            ->where('file_hash', $fileHash)
            ->where('created_at', '>', $thirtyDaysAgo)
            ->find();

        if ($duplicate) {
            throw new BusinessException('Duplicate file detected', 40901, 409);
        }

        // Store file to disk
        $uploadDir = runtime_path('storage') . $storagePath;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file->move($uploadDir, $filename);
        $diskPath = $uploadDir . '/' . $filename;

        // Apply watermark if enabled and file is a photo
        if ($isPhoto && env('MEDIA_WATERMARK_ENABLED', false)) {
            $this->applyWatermark($diskPath, $mimeType);
        }

        // Create Media record
        $media = new Media();
        $media->organization_id = $orgId;
        $media->user_id         = $userId;
        $media->parent_type     = $parentType;
        $media->parent_id       = $parentId;
        $media->file_path       = $fullPath;
        $media->file_name       = $filename;
        $media->mime_type       = $mimeType;
        $media->file_size       = $fileSize;
        $media->file_hash       = $fileHash;
        $media->file_type       = $isPhoto ? 'photo' : 'video';
        $media->save();

        return $media;
    }

    /**
     * Generate a signed URL for a media file.
     */
    public function generateSignedUrl(int $mediaId, ?int $expiryMinutes = null): string
    {
        if ($expiryMinutes === null) {
            $expiryMinutes = (int) env('MEDIA_URL_EXPIRY_MINUTES', 10);
        }

        $expires   = time() + ($expiryMinutes * 60);
        $secret    = (string) env('MEDIA_SIGN_SECRET', '');
        $signature = hash_hmac('sha256', $mediaId . $expires, $secret);

        return "/api/media/{$mediaId}?signature={$signature}&expires={$expires}";
    }

    /**
     * Serve a media file (return path and mime for streaming).
     */
    public function serve(int $mediaId): array
    {
        $media = Media::find($mediaId);
        if (!$media) {
            throw new NotFoundException('Media not found');
        }

        $diskPath = runtime_path('storage') . $media->file_path;

        return [
            'file_path' => $diskPath,
            'mime_type' => $media->mime_type,
        ];
    }

    /**
     * Generate a 200x200 thumbnail for a photo.
     */
    public function generateThumbnail(int $mediaId): string
    {
        $media = Media::find($mediaId);
        if (!$media) {
            throw new NotFoundException('Media not found');
        }

        if ($media->file_type !== 'photo') {
            throw new BusinessException('Thumbnails can only be generated for photos', 40901);
        }

        $sourcePath = runtime_path('storage') . $media->file_path;
        $thumbDir   = dirname($sourcePath) . '/thumbnails';
        $thumbPath  = $thumbDir . '/' . $media->file_name;

        // Return cached thumbnail if it exists
        if (file_exists($thumbPath)) {
            return $thumbPath;
        }

        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        // Load source image
        $sourceImage = $this->loadImage($sourcePath, $media->mime_type);
        if (!$sourceImage) {
            throw new BusinessException('Failed to process image', 40901);
        }

        $origWidth  = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // Create 200x200 thumbnail with aspect ratio preservation (crop to fill)
        $thumbWidth  = 200;
        $thumbHeight = 200;

        $ratio  = max($thumbWidth / $origWidth, $thumbHeight / $origHeight);
        $srcW   = (int) round($thumbWidth / $ratio);
        $srcH   = (int) round($thumbHeight / $ratio);
        $srcX   = (int) round(($origWidth - $srcW) / 2);
        $srcY   = (int) round(($origHeight - $srcH) / 2);

        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // Preserve transparency for PNG/GIF/WebP
        if (in_array($media->mime_type, ['image/png', 'image/gif', 'image/webp'])) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }

        imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0, 0,
            $srcX, $srcY,
            $thumbWidth, $thumbHeight,
            $srcW, $srcH
        );

        // Save thumbnail
        $this->saveImage($thumbnail, $thumbPath, $media->mime_type);

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return $thumbPath;
    }

    /**
     * Apply a watermark to a photo using GD.
     */
    public function applyWatermark(string $filePath, string $mimeType): void
    {
        $watermarkPath = (string) env('MEDIA_WATERMARK_IMAGE', '');
        if ($watermarkPath === '' || !file_exists($watermarkPath)) {
            return;
        }

        $image = $this->loadImage($filePath, $mimeType);
        if (!$image) {
            return;
        }

        $watermark = imagecreatefrompng($watermarkPath);
        if (!$watermark) {
            imagedestroy($image);
            return;
        }

        $imgWidth  = imagesx($image);
        $imgHeight = imagesy($image);
        $wmWidth   = imagesx($watermark);
        $wmHeight  = imagesy($watermark);

        // Position at bottom-right with 10px padding
        $destX = $imgWidth - $wmWidth - 10;
        $destY = $imgHeight - $wmHeight - 10;

        // Ensure non-negative positioning
        $destX = max(0, $destX);
        $destY = max(0, $destY);

        // Enable alpha blending and overlay watermark
        imagealphablending($image, true);
        imagecopy($image, $watermark, $destX, $destY, 0, 0, $wmWidth, $wmHeight);

        // Save back
        $this->saveImage($image, $filePath, $mimeType);

        imagedestroy($image);
        imagedestroy($watermark);
    }

    // ---- Private helpers ----

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // UUID v4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function loadImage(string $path, string $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            case 'image/webp':
                return imagecreatefromwebp($path);
            default:
                return null;
        }
    }

    private function saveImage($image, string $path, string $mimeType): void
    {
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($image, $path, 90);
                break;
            case 'image/png':
                imagepng($image, $path, 6);
                break;
            case 'image/gif':
                imagegif($image, $path);
                break;
            case 'image/webp':
                imagewebp($image, $path, 90);
                break;
        }
    }
}
