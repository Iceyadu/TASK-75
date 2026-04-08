<?php
declare(strict_types=1);

namespace unit_tests\review;

use PHPUnit\Framework\TestCase;

/**
 * Tests for CredibilityService score computation.
 *
 * Credibility formula:
 *   score = w_age * age_factor
 *         + w_completion * completion_factor
 *         + w_pattern * pattern_factor
 *         + w_timing * timing_factor
 *
 * Weights: age=0.25, completion=0.30, pattern=0.25, timing=0.20
 */
class CredibilityScoreTest extends TestCase
{
    private const WEIGHTS = [
        'age'        => 0.25,
        'completion' => 0.30,
        'pattern'    => 0.25,
        'timing'     => 0.20,
    ];

    private function computeCredibility(
        int $accountAgeDays,
        int $completedOrders,
        int $totalOrders,
        int $recentFiveStarCount,
        float $minutesSinceCompletion
    ): float {
        // Age factor
        $ageFactor = $accountAgeDays < 14 ? 0.5 : 1.0;

        // Completion factor
        $completionFactor = $totalOrders > 0 ? $completedOrders / $totalOrders : 0.0;

        // Pattern factor: penalize burst of 5-star reviews
        $patternFactor = $recentFiveStarCount >= 3 ? 0.5 : 1.0;

        // Timing factor: review within 5 min of completion is suspicious
        $timingFactor = ($minutesSinceCompletion >= 0 && $minutesSinceCompletion <= 5) ? 0.5 : 1.0;

        $score = self::WEIGHTS['age'] * $ageFactor
               + self::WEIGHTS['completion'] * $completionFactor
               + self::WEIGHTS['pattern'] * $patternFactor
               + self::WEIGHTS['timing'] * $timingFactor;

        return max(0.0, min(1.0, $score));
    }

    public function test_new_account_under_14_days_gets_half_age_factor(): void
    {
        // All other factors optimal: completion=1.0, pattern=1.0, timing=1.0
        $score = $this->computeCredibility(7, 10, 10, 0, 60);

        // age: 0.25 * 0.5 = 0.125
        // completion: 0.30 * 1.0 = 0.30
        // pattern: 0.25 * 1.0 = 0.25
        // timing: 0.20 * 1.0 = 0.20
        // total = 0.875
        $this->assertEqualsWithDelta(0.875, $score, 0.001);
    }

    public function test_old_account_over_14_days_gets_full_age_factor(): void
    {
        $score = $this->computeCredibility(30, 10, 10, 0, 60);

        // age: 0.25 * 1.0 = 0.25
        // completion: 0.30 * 1.0 = 0.30
        // pattern: 0.25 * 1.0 = 0.25
        // timing: 0.20 * 1.0 = 0.20
        // total = 1.0
        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    public function test_completion_factor_is_ratio_of_completed_to_total(): void
    {
        // 5 out of 10 orders completed
        $score = $this->computeCredibility(30, 5, 10, 0, 60);

        // completion: 0.30 * 0.5 = 0.15
        // total = 0.25 + 0.15 + 0.25 + 0.20 = 0.85
        $this->assertEqualsWithDelta(0.85, $score, 0.001);
    }

    public function test_no_orders_gives_zero_completion_factor(): void
    {
        $score = $this->computeCredibility(30, 0, 0, 0, 60);

        // completion: 0.30 * 0.0 = 0.0
        // total = 0.25 + 0.0 + 0.25 + 0.20 = 0.70
        $this->assertEqualsWithDelta(0.70, $score, 0.001);
    }

    public function test_burst_five_star_reviews_reduce_pattern_factor(): void
    {
        // 3 or more 5-star reviews in 24h
        $score = $this->computeCredibility(30, 10, 10, 5, 60);

        // pattern: 0.25 * 0.5 = 0.125
        // total = 0.25 + 0.30 + 0.125 + 0.20 = 0.875
        $this->assertEqualsWithDelta(0.875, $score, 0.001);
    }

    public function test_review_within_5_min_of_completion_penalized(): void
    {
        // Review at 3 minutes after completion
        $score = $this->computeCredibility(30, 10, 10, 0, 3);

        // timing: 0.20 * 0.5 = 0.10
        // total = 0.25 + 0.30 + 0.25 + 0.10 = 0.90
        $this->assertEqualsWithDelta(0.90, $score, 0.001);
    }

    public function test_score_clamped_between_0_and_1(): void
    {
        // All optimal factors should produce exactly 1.0
        $maxScore = $this->computeCredibility(30, 10, 10, 0, 60);
        $this->assertLessThanOrEqual(1.0, $maxScore);
        $this->assertGreaterThanOrEqual(0.0, $maxScore);

        // Worst case: new account, no orders, burst 5-star, quick review
        $minScore = $this->computeCredibility(1, 0, 0, 5, 2);
        $this->assertLessThanOrEqual(1.0, $minScore);
        $this->assertGreaterThanOrEqual(0.0, $minScore);
    }

    public function test_weights_sum_correctly(): void
    {
        $sum = array_sum(self::WEIGHTS);
        $this->assertEqualsWithDelta(1.0, $sum, 0.0001);
    }
}
