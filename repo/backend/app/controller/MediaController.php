<?php
declare(strict_types=1);

namespace app\controller;

use app\service\MediaService;
use think\Response;

class MediaController extends BaseController
{
    protected MediaService $mediaService;

    public function __construct()
    {
        parent::__construct();
        $this->mediaService = new MediaService();
    }

    /**
     * GET /api/media/:id
     * Serve the media file with correct Content-Type header.
     */
    public function show($id)
    {
        $result   = $this->mediaService->serve((int) $id);
        $filePath = $result['file_path'];
        $mimeType = $result['mime_type'];

        if (!file_exists($filePath)) {
            return json([
                'code'    => 40401,
                'message' => 'File not found on disk',
                'data'    => null,
            ], 404);
        }

        $content = file_get_contents($filePath);

        return Response::create($content, 'html', 200)->header([
            'Content-Type'        => $mimeType,
            'Content-Length'      => (string) strlen($content),
            'Content-Disposition' => 'inline',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    /**
     * GET /api/media/:id/thumbnail
     * Serve the thumbnail for a media file.
     */
    public function thumbnail($id)
    {
        $thumbPath = $this->mediaService->generateThumbnail((int) $id);

        if (!file_exists($thumbPath)) {
            return json([
                'code'    => 40401,
                'message' => 'Thumbnail not found',
                'data'    => null,
            ], 404);
        }

        $content  = file_get_contents($thumbPath);
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content);
        finfo_close($finfo);

        return Response::create($content, 'html', 200)->header([
            'Content-Type'        => $mimeType ?: 'image/jpeg',
            'Content-Length'      => (string) strlen($content),
            'Content-Disposition' => 'inline',
            'Cache-Control'       => 'public, max-age=86400',
        ]);
    }
}
