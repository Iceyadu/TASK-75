<?php
declare(strict_types=1);

namespace app\service;

use app\model\Order;
use app\model\Review;
use app\model\User;

class CredibilityService
{
    protected ModerationService $moderationService;

    public function __construct()
    {
        $this->moderationService = new ModerationService();
    }

    /**
     * Compute a credibility score for a user's review of an order.
     */
    public function compute(int $userId, int $orderId, int $orgId): float
    {
        $user  = User::find($userId);
        $order = Order::find($orderId);

        // Weights from env
        $wAge        = (float) env('CREDIBILITY_WEIGHT_ACCOUNT_AGE', 0.3);
        $wCompletion = (float) env('CREDIBILITY_WEIGHT_COMPLETION', 0.4);
        $wPattern    = (float) env('CREDIBILITY_WEIGHT_PATTERN', 0.3);

        // 1. age_factor: account older than 14 days => 1.0, else 0.5
        $ageFactor = 1.0;
        if ($user) {
            $createdAt = strtotime($user->create_time ?? $user->created_at ?? 'now');
            $daysSince = (time() - $createdAt) / 86400;
            $ageFactor = $daysSince > 14 ? 1.0 : 0.5;
        }

        // 2. completion_factor: completed / total orders
        $completionFactor = 0.0;
        if ($user) {
            $totalOrders = Order::where(function ($query) use ($userId) {
                $query->where('passenger_id', $userId)
                    ->whereOr('driver_id', $userId);
            })->where('organization_id', $orgId)->count();

            $completedOrders = Order::where(function ($query) use ($userId) {
                $query->where('passenger_id', $userId)
                    ->whereOr('driver_id', $userId);
            })->where('organization_id', $orgId)
              ->where('status', 'completed')
              ->count();

            $completionFactor = $totalOrders > 0 ? ($completedOrders / $totalOrders) : 0.0;
        }

        // 3. pattern_factor: start at 1.0, check anomalies
        $patternFactor = 1.0;

        // Anomaly check 1: Count 5-star reviews in the last hour (MySQL DATETIME compares use local format).
        $oneHourAgo    = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $fiveStarCount = Review::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->where('rating', 5)
            ->where('created_at', '>', $oneHourAgo)
            ->count();

        if ($fiveStarCount >= 3) {
            $patternFactor -= 0.25 * ($fiveStarCount - 2);
        }

        // Anomaly check 2: Review posted within 5 min of order completion
        if ($order && $order->completed_at) {
            $completedTime = strtotime($order->completed_at);
            $now           = time();
            $minutesSince  = ($now - $completedTime) / 60;

            if ($minutesSince <= 5) {
                $patternFactor -= 0.25;
            }
        }

        // Anomaly check 3: Near-identical text (trigram > 0.9) with same rating
        $recentReviews = Review::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->where('created_at', '>', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->select();

        // We need the current review text if it exists; check if there's a review for this order
        $currentReview = Review::where('user_id', $userId)
            ->where('order_id', $orderId)
            ->find();

        if ($currentReview) {
            foreach ($recentReviews as $recent) {
                if ($recent->id === $currentReview->id) {
                    continue;
                }
                if ((int) $recent->rating === (int) $currentReview->rating) {
                    $similarity = $this->moderationService->trigramJaccard(
                        $currentReview->text,
                        $recent->text
                    );
                    if ($similarity > 0.9) {
                        $patternFactor -= 0.25;
                        break; // Only subtract once for this anomaly
                    }
                }
            }
        }

        // Clamp pattern_factor to [0.0, 1.0]
        $patternFactor = max(0.0, min(1.0, $patternFactor));

        // 5. Final score
        $score = ($wAge * $ageFactor) + ($wCompletion * $completionFactor) + ($wPattern * $patternFactor);

        // Clamp to [0.0, 1.0]
        return max(0.0, min(1.0, round($score, 4)));
    }

    /**
     * Recompute credibility scores for all reviews in an organization.
     */
    public function recomputeAll(int $orgId): int
    {
        $reviews = Review::where('organization_id', $orgId)->select();
        $count   = 0;

        foreach ($reviews as $review) {
            $newScore = $this->compute(
                (int) $review->user_id,
                (int) $review->order_id,
                $orgId
            );

            $review->credibility_score = $newScore;
            $review->save();
            $count++;
        }

        return $count;
    }
}
