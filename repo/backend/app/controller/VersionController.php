<?php
declare(strict_types=1);

namespace app\controller;

use app\service\ListingService;

class VersionController extends BaseController
{
    protected ListingService $listingService;

    public function __construct()
    {
        $this->listingService = new ListingService();
    }

    /**
     * GET /api/listings/:id/versions
     */
    public function index($id)
    {
        $versions = $this->listingService->getVersions((int) $id);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $versions->toArray(),
        ], 200);
    }

    /**
     * GET /api/listings/:id/versions/:version
     */
    public function show($id, $version)
    {
        $listingVersion = $this->listingService->getVersion((int) $id, (int) $version);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $listingVersion->toArray(),
        ], 200);
    }

    /**
     * GET /api/listings/:id/versions/:v1/diff/:v2
     */
    public function diff($id, $v1, $v2)
    {
        $v1 = (int) $v1;
        $v2 = (int) $v2;

        $changes = $this->listingService->diffVersions((int) $id, $v1, $v2);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $changes,
        ], 200);
    }
}
