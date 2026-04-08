<?php
declare(strict_types=1);

namespace unit_tests\governance;

use PHPUnit\Framework\TestCase;

/**
 * Tests for data quality metric computations.
 */
class QualityMetricsTest extends TestCase
{
    /**
     * Compute listing_completeness: % of listings where description,
     * baggage_notes, and tags are all non-null.
     */
    private function computeListingCompleteness(array $listings): float
    {
        if (empty($listings)) {
            return 1.0;
        }

        $complete = 0;
        foreach ($listings as $listing) {
            if ($listing['description'] !== null
                && $listing['baggage_notes'] !== null
                && $listing['tags'] !== null
            ) {
                $complete++;
            }
        }

        return round($complete / count($listings), 4);
    }

    /**
     * Compute stale_listing_rate: % of active listings where last_activity_at
     * is older than 7 days or NULL.
     */
    private function computeStaleListingRate(array $activeListings): float
    {
        if (empty($activeListings)) {
            return 0.0;
        }

        $sevenDaysAgo = strtotime('-7 days');
        $stale = 0;

        foreach ($activeListings as $listing) {
            if ($listing['last_activity_at'] === null
                || strtotime($listing['last_activity_at']) < $sevenDaysAgo
            ) {
                $stale++;
            }
        }

        return round($stale / count($activeListings), 4);
    }

    /**
     * Compute moderation_queue_depth: count of pending items.
     */
    private function computeModerationQueueDepth(array $queueItems): int
    {
        return count(array_filter($queueItems, fn($item) => $item['status'] === 'pending'));
    }

    public function test_listing_completeness_calculation(): void
    {
        $listings = [
            ['description' => 'Full', 'baggage_notes' => 'Yes', 'tags' => 'tag1'],
            ['description' => 'Full', 'baggage_notes' => 'Yes', 'tags' => 'tag2'],
            ['description' => null,   'baggage_notes' => 'Yes', 'tags' => 'tag3'], // Incomplete
            ['description' => 'Full', 'baggage_notes' => null,  'tags' => 'tag4'], // Incomplete
        ];

        $completeness = $this->computeListingCompleteness($listings);

        // 2 out of 4 are complete
        $this->assertEqualsWithDelta(0.5, $completeness, 0.001);
    }

    public function test_stale_listing_rate_calculation(): void
    {
        $listings = [
            ['last_activity_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],    // Fresh
            ['last_activity_at' => date('Y-m-d H:i:s', strtotime('-10 days'))],   // Stale
            ['last_activity_at' => null],                                          // Stale (null)
            ['last_activity_at' => date('Y-m-d H:i:s', strtotime('-3 days'))],    // Fresh
        ];

        $staleRate = $this->computeStaleListingRate($listings);

        // 2 out of 4 are stale
        $this->assertEqualsWithDelta(0.5, $staleRate, 0.001);
    }

    public function test_moderation_queue_depth(): void
    {
        $queueItems = [
            ['id' => 1, 'status' => 'pending'],
            ['id' => 2, 'status' => 'approved'],
            ['id' => 3, 'status' => 'pending'],
            ['id' => 4, 'status' => 'rejected'],
            ['id' => 5, 'status' => 'pending'],
        ];

        $depth = $this->computeModerationQueueDepth($queueItems);
        $this->assertEquals(3, $depth);
    }
}
