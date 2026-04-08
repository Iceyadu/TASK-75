<?php
declare(strict_types=1);

namespace app\command;

use app\model\DataLineage;
use app\model\Order;
use app\model\Organization;
use app\model\Review;
use app\model\User;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

/**
 * Recompute credibility scores for all reviews.
 *
 * For each organization, loads all reviews with their associated users and
 * orders, then recalculates the credibility_score using the same formula
 * as CredibilityService::compute().
 *
 * Credibility formula:
 *   score = w_age * age_factor
 *         + w_completion * completion_factor
 *         + w_pattern * pattern_factor
 *         + w_timing * timing_factor
 *
 * Weights: age=0.25, completion=0.30, pattern=0.25, timing=0.20
 */
class CredibilityRecomputeCommand extends Command
{
    /** @var float[] */
    private const WEIGHTS = [
        'age'        => 0.25,
        'completion' => 0.30,
        'pattern'    => 0.25,
        'timing'     => 0.20,
    ];

    protected function configure(): void
    {
        $this->setName('credibility:recompute')
             ->setDescription('Recompute credibility scores for all reviews');
    }

    protected function execute(Input $input, Output $output): int
    {
        $runId = $this->generateUuid();
        $totalReviews = 0;

        $organizations = Organization::select();

        foreach ($organizations as $org) {
            $orgId = $org->id;
            $stepStartedAt = date('Y-m-d H:i:s');

            $reviews = Review::where('organization_id', $orgId)->select();

            foreach ($reviews as $review) {
                $user = User::find($review->user_id);
                if (!$user) {
                    continue;
                }

                $score = $this->computeCredibility($review, $user, $orgId);
                $review->credibility_score = round($score, 4);
                $review->save();

                $totalReviews++;
            }

            $stepCompletedAt = date('Y-m-d H:i:s');

            // Record lineage
            DataLineage::create([
                'organization_id' => $orgId,
                'job_name'        => 'credibility:recompute',
                'run_id'          => $runId,
                'step'            => 'credibility_recompute',
                'input_count'     => count($reviews),
                'output_count'    => count($reviews),
                'removed_count'   => 0,
                'details'         => json_encode([
                    'started_at'   => $stepStartedAt,
                    'completed_at' => $stepCompletedAt,
                ]),
                'executed_at'     => $stepCompletedAt,
            ]);
        }

        Log::info("credibility:recompute completed: recomputed {$totalReviews} reviews (run_id={$runId})");
        $output->writeln("Recomputed credibility for {$totalReviews} reviews.");

        return 0;
    }

    /**
     * Compute credibility score for a single review.
     */
    private function computeCredibility(Review $review, User $user, int $orgId): float
    {
        // Age factor: account age < 14 days => 0.5, otherwise 1.0
        $accountAgeDays = (time() - strtotime($user->created_at)) / 86400;
        $ageFactor = $accountAgeDays < 14 ? 0.5 : 1.0;

        // Completion factor: ratio of completed orders to total orders
        $totalOrders = Order::where('organization_id', $orgId)
            ->where(function ($query) use ($user) {
                $query->where('passenger_id', $user->id)
                    ->whereOr('driver_id', $user->id);
            })
            ->count();
        $completedOrders = Order::where('organization_id', $orgId)
            ->where(function ($query) use ($user) {
                $query->where('passenger_id', $user->id)
                    ->whereOr('driver_id', $user->id);
            })
            ->where('status', 'completed')
            ->count();
        $completionFactor = $totalOrders > 0 ? $completedOrders / $totalOrders : 0.0;

        // Pattern factor: penalize burst of 5-star reviews
        $recentFiveStarCount = Review::where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->where('rating', 5)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->count();
        $patternFactor = $recentFiveStarCount >= 3 ? 0.5 : 1.0;

        // Timing factor: review within 5 minutes of order completion is suspicious
        $order = Order::find($review->order_id);
        $timingFactor = 1.0;
        if ($order && $order->completed_at) {
            $completionTime = strtotime($order->completed_at);
            $reviewTime = strtotime($review->created_at);
            $diffMinutes = ($reviewTime - $completionTime) / 60;
            if ($diffMinutes >= 0 && $diffMinutes <= 5) {
                $timingFactor = 0.5;
            }
        }

        // Weighted sum, clamped to [0, 1]
        $score = self::WEIGHTS['age'] * $ageFactor
               + self::WEIGHTS['completion'] * $completionFactor
               + self::WEIGHTS['pattern'] * $patternFactor
               + self::WEIGHTS['timing'] * $timingFactor;

        return max(0.0, min(1.0, $score));
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
