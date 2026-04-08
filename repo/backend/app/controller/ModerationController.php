<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ValidationException;
use app\model\ModerationQueue;
use app\service\ModerationService;

class ModerationController extends BaseController
{
    protected ModerationService $moderationService;

    public function __construct()
    {
        parent::__construct();
        $this->moderationService = new ModerationService();
    }

    /**
     * GET /api/moderation
     */
    public function index()
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = min(100, max(1, (int) $this->request->get('per_page', 15)));
        $type    = $this->request->get('type', '');
        $reason  = $this->request->get('flag_reason', '');
        $sort    = $this->request->get('sort', 'newest');

        $query = ModerationQueue::where('organization_id', $this->request->orgId);

        if ($type !== '') {
            $query->where('item_type', $type);
        }
        if ($reason !== '') {
            $query->where('flag_reason', 'like', '%' . $reason . '%');
        }

        switch ($sort) {
            case 'oldest':
                $query->order('created_at', 'asc');
                break;
            case 'credibility_asc':
                $query->order('credibility_score', 'asc');
                break;
            case 'credibility_desc':
                $query->order('credibility_score', 'desc');
                break;
            case 'newest':
            default:
                $query->order('created_at', 'desc');
                break;
        }

        $total   = $query->count();
        $items   = $query->page($page, $perPage)->select();
        $lastPage = (int) ceil($total / $perPage);

        // Enrich with content preview and user info
        $enriched = [];
        foreach ($items as $item) {
            $data = $item->toArray();

            // Load content preview based on item type
            if ($item->item_type === 'review') {
                $review = \app\model\Review::with(['user'])->find($item->item_id);
                if ($review) {
                    $data['content_preview'] = mb_substr($review->text ?? '', 0, 200);
                    $data['user_info']       = $review->user ? [
                        'id'   => $review->user->id,
                        'name' => $review->user->name,
                    ] : null;
                }
            } elseif ($item->item_type === 'listing') {
                $listing = \app\model\Listing::with(['user'])->find($item->item_id);
                if ($listing) {
                    $data['content_preview'] = mb_substr($listing->title ?? '', 0, 200);
                    $data['user_info']       = $listing->user ? [
                        'id'   => $listing->user->id,
                        'name' => $listing->user->name,
                    ] : null;
                }
            }

            $enriched[] = $data;
        }

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $enriched,
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ], 200);
    }

    /**
     * POST /api/moderation/:id/approve
     */
    public function approve($id)
    {
        $this->moderationService->approve(
            (int) $id,
            (int) $this->request->user->id
        );

        return json([
            'code'    => 0,
            'message' => 'Item approved',
            'data'    => null,
        ], 200);
    }

    /**
     * POST /api/moderation/:id/reject
     */
    public function reject($id)
    {
        $reason = $this->request->post('reason', '');
        if ($reason === '') {
            throw new ValidationException('Rejection reason is required', [
                'reason' => 'Please provide a reason for rejection',
            ]);
        }

        $this->moderationService->reject(
            (int) $id,
            (int) $this->request->user->id,
            $reason
        );

        return json([
            'code'    => 0,
            'message' => 'Item rejected',
            'data'    => null,
        ], 200);
    }

    /**
     * POST /api/moderation/:id/escalate
     */
    public function escalate($id)
    {
        $this->moderationService->escalate(
            (int) $id,
            (int) $this->request->user->id
        );

        return json([
            'code'    => 0,
            'message' => 'Item escalated',
            'data'    => null,
        ], 200);
    }
}
