<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\model\Listing;
use app\service\GovernanceService;
use app\service\ListingService;

class ListingController extends BaseController
{
    protected ListingService $listingService;
    protected GovernanceService $governanceService;

    public function __construct()
    {
        $this->listingService    = new ListingService();
        $this->governanceService = new GovernanceService();
    }

    /**
     * GET /api/listings
     */
    public function index()
    {
        $filters = [
            'q'               => $this->request->get('q', ''),
            'status'          => $this->request->get('status', ''),
            'vehicle_type'    => $this->request->get('vehicle_type', ''),
            'rider_count_min' => $this->request->get('rider_count_min'),
            'rider_count_max' => $this->request->get('rider_count_max'),
            'sort'            => $this->request->get('sort', 'newest'),
            'page'            => $this->request->get('page', 1),
            'per_page'        => $this->request->get('per_page', 15),
        ];

        $result = $this->listingService->search($this->request->orgId, $filters);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => [
                'listings'      => $result['listings'],
                'highlights'    => $result['highlights'],
                'recent_active' => $result['recent_active'],
                'suggestions'   => $result['suggestions'],
                'did_you_mean'  => $result['did_you_mean'],
            ],
            'meta' => $result['meta'],
        ], 200);
    }

    /**
     * POST /api/listings
     */
    public function store()
    {
        validate($this->request->post(), 'app\validate\ListingValidate.create');

        $listing = $this->listingService->create(
            $this->request->orgId,
            (int) $this->request->user->id,
            $this->request->post()
        );

        return json([
            'code'    => 0,
            'message' => 'Listing created successfully',
            'data'    => $listing->toArray(),
        ], 201);
    }

    /**
     * GET /api/listings/:id
     */
    public function show($id)
    {
        $listing = Listing::with(['user'])->find($id);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }

        if ((int) $listing->organization_id !== $this->request->orgId) {
            throw new ForbiddenException('You do not have access to this resource');
        }

        // Record view event
        $this->governanceService->recordEvent(
            $this->request->orgId,
            (int) $this->request->user->id,
            'listing_view',
            'listing',
            (int) $id,
            ['ip' => $this->request->ip()]
        );

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $listing->toArray(),
        ], 200);
    }

    /**
     * PUT /api/listings/:id
     */
    public function update($id)
    {
        $listing = Listing::find($id);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }
        if ((int) $listing->user_id !== (int) $this->request->user->id) {
            throw new ForbiddenException('You do not own this listing');
        }

        validate($this->request->post(), 'app\validate\ListingValidate.update');

        $updated = $this->listingService->update(
            (int) $id,
            (int) $this->request->user->id,
            $this->request->post()
        );

        return json([
            'code'    => 0,
            'message' => 'Listing updated successfully',
            'data'    => $updated->toArray(),
        ], 200);
    }

    /**
     * DELETE /api/listings/:id
     */
    public function destroy($id)
    {
        $this->listingService->destroy(
            (int) $id,
            (int) $this->request->user->id
        );

        return json([
            'code'    => 0,
            'message' => 'Listing deleted successfully',
            'data'    => null,
        ], 200);
    }

    /**
     * POST /api/listings/:id/publish
     */
    public function publish($id)
    {
        $listing = Listing::find($id);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }
        if ((int) $listing->user_id !== (int) $this->request->user->id) {
            throw new ForbiddenException('You do not own this listing');
        }

        $published = $this->listingService->publish(
            (int) $id,
            (int) $this->request->user->id
        );

        return json([
            'code'    => 0,
            'message' => 'Listing published successfully',
            'data'    => $published->toArray(),
        ], 200);
    }

    /**
     * POST /api/listings/:id/unpublish
     */
    public function unpublish($id)
    {
        $listing = Listing::find($id);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }

        $isOwner     = (int) $listing->user_id === (int) $this->request->user->id;
        $isModerator = $this->request->user->isModerator();

        if (!$isOwner && !$isModerator) {
            throw new ForbiddenException('You do not have permission to unpublish this listing');
        }

        $unpublished = $this->listingService->unpublish(
            (int) $id,
            (int) $this->request->user->id,
            $isModerator && !$isOwner
        );

        return json([
            'code'    => 0,
            'message' => 'Listing unpublished successfully',
            'data'    => $unpublished->toArray(),
        ], 200);
    }

    /**
     * POST /api/listings/:id/flag
     */
    public function flag($id)
    {
        $listing = Listing::find($id);
        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }

        if ((int) $listing->organization_id !== $this->request->orgId) {
            throw new ForbiddenException('You do not have access to this resource');
        }

        $reason = $this->request->post('reason', 'manual_flag');

        $moderationService = new \app\service\ModerationService();
        $moderationService->flagItem(
            $this->request->orgId,
            'listing',
            (int) $id,
            'manual_flag',
            $reason,
            null
        );

        return json([
            'code'    => 0,
            'message' => 'Listing flagged for review',
            'data'    => null,
        ], 200);
    }

    /**
     * POST /api/listings/bulk-close
     */
    public function bulkClose()
    {
        $listingIds = $this->request->post('listing_ids', []);
        $reason     = $this->request->post('reason', '');

        $result = $this->listingService->bulkClose(
            $this->request->orgId,
            $listingIds,
            $reason
        );

        return json([
            'code'    => 0,
            'message' => 'Bulk close completed',
            'data'    => $result,
        ], 200);
    }
}
