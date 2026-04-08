<?php
declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\model\Order;
use app\model\Review;

class ReviewService
{
    protected ModerationService $moderationService;
    protected CredibilityService $credibilityService;
    protected MediaService $mediaService;
    protected AuditService $auditService;

    public function __construct()
    {
        $this->moderationService   = new ModerationService();
        $this->credibilityService  = new CredibilityService();
        $this->mediaService        = new MediaService();
        $this->auditService        = new AuditService();
    }

    /**
     * List reviews with filters and pagination, including media with signed URLs.
     */
    public function list(int $orgId, array $filters): array
    {
        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        $query = Review::where('organization_id', $orgId);

        if (!empty($filters['listing_id'])) {
            $query->where('listing_id', (int) $filters['listing_id']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['order_id'])) {
            $query->where('order_id', (int) $filters['order_id']);
        }

        $query->order('created_at', 'desc');

        $total   = $query->count();
        $reviews = $query->page($page, $perPage)->with(['media'])->select();
        $lastPage = (int) ceil($total / $perPage);

        // Attach signed URLs to media
        $reviewsArray = $reviews->toArray();
        foreach ($reviewsArray as &$review) {
            if (!empty($review['media'])) {
                foreach ($review['media'] as &$media) {
                    $media['signed_url'] = $this->mediaService->generateSignedUrl((int) $media['id']);
                }
                unset($media);
            }
        }
        unset($review);

        return [
            'reviews' => $reviewsArray,
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Create a review for a completed order.
     */
    public function create(int $orgId, int $userId, array $data, array $files = []): Review
    {
        $orderId = (int) $data['order_id'];

        $order = Order::where('id', $orderId)
            ->where('organization_id', $orgId)
            ->find();

        if (!$order) {
            throw new NotFoundException('Order not found');
        }

        if ($order->status !== 'completed') {
            throw new BusinessException('Reviews can only be created for completed orders', 40901, 409);
        }

        if ((int) $order->passenger_id !== $userId && (int) $order->driver_id !== $userId) {
            throw new ForbiddenException('You are not a party to this order');
        }

        // Check for existing review by this user for this order
        $existingReview = Review::where('order_id', $orderId)
            ->where('user_id', $userId)
            ->find();

        if ($existingReview) {
            throw new BusinessException('You have already reviewed this order', 40901, 409);
        }

        // Check for duplicate text
        $isDuplicate = $this->moderationService->checkDuplicateText($userId, $data['text'], $orgId);

        // Compute credibility score
        $credibilityScore = $this->credibilityService->compute($userId, $orderId, $orgId);

        // Run sensitive-word check
        $sensitiveCheck = $this->moderationService->checkSensitiveWords($data['text']);

        $needsModeration = $isDuplicate || ($sensitiveCheck && $sensitiveCheck['matched']);

        $review = new Review();
        $review->organization_id   = $orgId;
        $review->user_id           = $userId;
        $review->order_id          = $orderId;
        $review->listing_id        = (int) $order->listing_id;
        $review->rating            = (int) $data['rating'];
        $review->text              = $data['text'];
        $review->credibility_score = $credibilityScore;
        $review->status            = $needsModeration ? 'pending' : 'published';
        $review->save();

        // Handle media uploads
        foreach ($files as $file) {
            $this->mediaService->upload($orgId, $userId, $file, 'review', $review->id);
        }

        // If flagged, add to moderation queue
        if ($needsModeration) {
            $flagReason = '';
            $flagDetails = '';

            if ($isDuplicate) {
                $flagReason  = 'duplicate_text';
                $flagDetails = 'Review text is too similar to a recent review by this user';
            }

            if ($sensitiveCheck && $sensitiveCheck['matched']) {
                $flagReason  = $flagReason ? $flagReason . ',sensitive_words' : 'sensitive_words';
                $flagDetails .= ($flagDetails ? '; ' : '') . 'Matched words: ' . implode(', ', $sensitiveCheck['words']);
            }

            $this->moderationService->flagItem(
                $orgId,
                'review',
                $review->id,
                $flagReason,
                $flagDetails,
                $credibilityScore
            );
        }

        // Record audit log
        $this->auditService->log(
            $orgId,
            $userId,
            'review.create',
            'review',
            $review->id,
            null,
            ['rating' => $review->rating, 'status' => $review->status]
        );

        return $review;
    }

    /**
     * Update a review (owner only, not yet moderated).
     */
    public function update(int $reviewId, int $userId, array $data): Review
    {
        $review = Review::find($reviewId);
        if (!$review) {
            throw new NotFoundException('Review not found');
        }
        if ((int) $review->user_id !== $userId) {
            throw new ForbiddenException('You do not own this review');
        }
        if ($review->status !== 'published') {
            throw new BusinessException('Only published reviews can be edited', 40901, 409);
        }

        $oldValues = ['rating' => $review->rating, 'text' => $review->text];

        if (isset($data['rating'])) {
            $review->rating = (int) $data['rating'];
        }
        if (isset($data['text'])) {
            $review->text = $data['text'];
        }

        // Re-run checks
        $sensitiveCheck = $this->moderationService->checkSensitiveWords($review->text);
        $isDuplicate    = $this->moderationService->checkDuplicateText($userId, $review->text, (int) $review->organization_id);

        if (($sensitiveCheck && $sensitiveCheck['matched']) || $isDuplicate) {
            $review->status = 'pending';

            $flagReason  = $isDuplicate ? 'duplicate_text' : '';
            $flagDetails = $isDuplicate ? 'Updated review text is similar to a recent review' : '';

            if ($sensitiveCheck && $sensitiveCheck['matched']) {
                $flagReason  = $flagReason ? $flagReason . ',sensitive_words' : 'sensitive_words';
                $flagDetails .= ($flagDetails ? '; ' : '') . 'Matched words: ' . implode(', ', $sensitiveCheck['words']);
            }

            $this->moderationService->flagItem(
                (int) $review->organization_id,
                'review',
                $review->id,
                $flagReason,
                $flagDetails,
                $review->credibility_score
            );
        }

        $review->save();

        return $review;
    }

    /**
     * Delete a review (owner only).
     */
    public function destroy(int $reviewId, int $userId): void
    {
        $review = Review::find($reviewId);
        if (!$review) {
            throw new NotFoundException('Review not found');
        }
        if ((int) $review->user_id !== $userId) {
            throw new ForbiddenException('You do not own this review');
        }

        $review->delete();
    }
}
