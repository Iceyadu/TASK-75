<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\model\Review;
use app\service\ReviewService;

class ReviewController extends BaseController
{
    protected ReviewService $reviewService;

    public function __construct()
    {
        parent::__construct();
        $this->reviewService = new ReviewService();
    }

    /**
     * GET /api/reviews
     */
    public function index()
    {
        $filters = [
            'listing_id' => $this->request->get('listing_id'),
            'user_id'    => $this->request->get('user_id'),
            'order_id'   => $this->request->get('order_id'),
            'page'       => $this->request->get('page', 1),
            'per_page'   => $this->request->get('per_page', 15),
        ];

        $result = $this->reviewService->list($this->request->orgId, $filters);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $result['reviews'],
            'meta'    => $result['meta'],
        ], 200);
    }

    /**
     * POST /api/reviews
     */
    public function store()
    {
        validate('app\validate\ReviewValidate.create')->check($this->request->post());

        $files = $this->request->file('files') ?? [];
        if (!is_array($files)) {
            $files = [$files];
        }

        $review = $this->reviewService->create(
            $this->request->orgId,
            (int) $this->request->user->id,
            $this->request->post(),
            $files
        );

        return json([
            'code'    => 0,
            'message' => 'Review created successfully',
            'data'    => $review->toArray(),
        ], 201);
    }

    /**
     * PUT /api/reviews/:id
     */
    public function update($id)
    {
        $review = Review::find($id);
        if (!$review) {
            throw new NotFoundException('Review not found');
        }
        if ((int) $review->user_id !== (int) $this->request->user->id) {
            throw new ForbiddenException('You do not own this review');
        }

        validate('app\validate\ReviewValidate.update')->check($this->request->post());

        $updated = $this->reviewService->update(
            (int) $id,
            (int) $this->request->user->id,
            $this->request->post()
        );

        return json([
            'code'    => 0,
            'message' => 'Review updated successfully',
            'data'    => $updated->toArray(),
        ], 200);
    }

    /**
     * DELETE /api/reviews/:id
     */
    public function destroy($id)
    {
        $review = Review::find($id);
        if (!$review) {
            throw new NotFoundException('Review not found');
        }
        if ((int) $review->user_id !== (int) $this->request->user->id) {
            throw new ForbiddenException('You do not own this review');
        }

        $this->reviewService->destroy(
            (int) $id,
            (int) $this->request->user->id
        );

        return json([
            'code'    => 0,
            'message' => 'Review deleted successfully',
            'data'    => null,
        ], 200);
    }
}
