<?php
declare(strict_types=1);

namespace unit_tests\listing;

use PHPUnit\Framework\TestCase;

/**
 * Tests for ListingService state transitions.
 *
 * Simulates the listing workflow without database access.
 */
class ListingWorkflowTest extends TestCase
{
    /** Valid statuses for listings */
    private const STATUSES = ['draft', 'active', 'matched', 'in_progress', 'completed', 'canceled', 'disputed'];

    /** Allowed transitions: from => [to, ...] */
    private const TRANSITIONS = [
        'draft'       => ['active', 'canceled'],
        'active'      => ['draft', 'matched', 'canceled'],
        'matched'     => ['active', 'in_progress', 'canceled'],
        'in_progress' => ['completed', 'disputed'],
        'completed'   => ['disputed'],
        'canceled'    => [],
        'disputed'    => ['resolved'],
    ];

    /**
     * Simulated listing.
     */
    private function makeListing(array $overrides = []): array
    {
        return array_merge([
            'id'              => 1,
            'user_id'         => 10,
            'organization_id' => 1,
            'status'          => 'draft',
            'title'           => null,
            'description'     => null,
            'departure'       => null,
            'destination'     => null,
            'rider_count'     => null,
            'vehicle_type'    => null,
            'version'         => 1,
        ], $overrides);
    }

    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    private function publish(array &$listing): void
    {
        if ($listing['status'] !== 'draft') {
            throw new \RuntimeException('Can only publish from draft status', 40001);
        }

        // Check required fields
        $required = ['title', 'description', 'departure', 'destination', 'rider_count', 'vehicle_type'];
        foreach ($required as $field) {
            if (empty($listing[$field])) {
                throw new \RuntimeException("Field '{$field}' is required for publishing", 40001);
            }
        }

        $listing['status'] = 'active';
    }

    private function unpublish(array &$listing): void
    {
        if ($listing['status'] !== 'active') {
            throw new \RuntimeException('Can only unpublish active listings', 40001);
        }
        $listing['status'] = 'draft';
    }

    private function edit(array &$listing, int $userId, array $changes): void
    {
        if ($listing['status'] === 'in_progress') {
            throw new \RuntimeException('Cannot edit in-progress listing', 40001);
        }
        if ($listing['user_id'] !== $userId) {
            throw new \RuntimeException('Only owner can edit', 40301);
        }
        foreach ($changes as $key => $value) {
            $listing[$key] = $value;
        }
        $listing['version']++;
    }

    private function delete(array $listing): void
    {
        if ($listing['status'] !== 'draft') {
            throw new \RuntimeException('Only draft listings can be deleted', 40001);
        }
    }

    private function bulkClose(array &$listings): int
    {
        $closed = 0;
        foreach ($listings as &$listing) {
            if ($listing['status'] === 'active') {
                $listing['status'] = 'canceled';
                $closed++;
            }
            // Skip matched, in_progress, etc.
        }
        return $closed;
    }

    public function test_create_listing_starts_as_draft(): void
    {
        $listing = $this->makeListing();
        $this->assertEquals('draft', $listing['status']);
    }

    public function test_publish_transitions_draft_to_active(): void
    {
        $listing = $this->makeListing([
            'status'       => 'draft',
            'title'        => 'Morning Commute',
            'description'  => 'Daily ride downtown',
            'departure'    => '123 Main St',
            'destination'  => '456 Work Ave',
            'rider_count'  => 3,
            'vehicle_type' => 'sedan',
        ]);

        $this->publish($listing);
        $this->assertEquals('active', $listing['status']);
    }

    public function test_publish_requires_all_fields(): void
    {
        $listing = $this->makeListing(['status' => 'draft', 'title' => 'Incomplete']);

        $this->expectException(\RuntimeException::class);
        $this->publish($listing);
    }

    public function test_unpublish_transitions_active_to_draft(): void
    {
        $listing = $this->makeListing(['status' => 'active']);
        $this->unpublish($listing);
        $this->assertEquals('draft', $listing['status']);
    }

    public function test_cannot_edit_in_progress_listing(): void
    {
        $listing = $this->makeListing(['status' => 'in_progress']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40001);
        $this->edit($listing, 10, ['title' => 'New Title']);
    }

    public function test_only_owner_can_edit(): void
    {
        $listing = $this->makeListing(['status' => 'draft', 'user_id' => 10]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40301);
        $this->edit($listing, 999, ['title' => 'Hacked Title']);
    }

    public function test_only_draft_can_be_deleted(): void
    {
        $draftListing = $this->makeListing(['status' => 'draft']);
        $this->delete($draftListing); // Should not throw

        $activeListing = $this->makeListing(['status' => 'active']);
        $this->expectException(\RuntimeException::class);
        $this->delete($activeListing);
    }

    public function test_bulk_close_only_affects_active_listings(): void
    {
        $listings = [
            $this->makeListing(['id' => 1, 'status' => 'active']),
            $this->makeListing(['id' => 2, 'status' => 'draft']),
            $this->makeListing(['id' => 3, 'status' => 'active']),
            $this->makeListing(['id' => 4, 'status' => 'completed']),
        ];

        $closed = $this->bulkClose($listings);

        $this->assertEquals(2, $closed);
        $this->assertEquals('canceled', $listings[0]['status']);
        $this->assertEquals('draft', $listings[1]['status']);
        $this->assertEquals('canceled', $listings[2]['status']);
        $this->assertEquals('completed', $listings[3]['status']);
    }

    public function test_bulk_close_skips_matched_listings(): void
    {
        $listings = [
            $this->makeListing(['id' => 1, 'status' => 'matched']),
            $this->makeListing(['id' => 2, 'status' => 'active']),
        ];

        $closed = $this->bulkClose($listings);

        $this->assertEquals(1, $closed);
        $this->assertEquals('matched', $listings[0]['status']);
        $this->assertEquals('canceled', $listings[1]['status']);
    }
}
