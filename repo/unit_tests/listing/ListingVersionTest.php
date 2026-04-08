<?php
declare(strict_types=1);

namespace unit_tests\listing;

use PHPUnit\Framework\TestCase;

/**
 * Tests for listing version tracking logic.
 */
class ListingVersionTest extends TestCase
{
    /** Simulated version store */
    private array $versions = [];
    private int $nextVersionId = 1;

    private function createListing(array $data): array
    {
        $listing = array_merge([
            'id'           => 1,
            'title'        => 'Original Title',
            'description'  => 'Original Description',
            'departure'    => '123 Main St',
            'destination'  => '456 Work Ave',
            'rider_count'  => 3,
            'vehicle_type' => 'sedan',
            'tags'         => 'commute,daily',
            'version'      => 1,
        ], $data);

        // Create version 1 snapshot
        $this->createVersionSnapshot($listing);

        return $listing;
    }

    private function createVersionSnapshot(array $listing): void
    {
        $this->versions[$this->nextVersionId] = [
            'id'          => $this->nextVersionId,
            'listing_id'  => $listing['id'],
            'version'     => $listing['version'],
            'snapshot'    => [
                'title'        => $listing['title'],
                'description'  => $listing['description'],
                'departure'    => $listing['departure'],
                'destination'  => $listing['destination'],
                'rider_count'  => $listing['rider_count'],
                'vehicle_type' => $listing['vehicle_type'],
                'tags'         => $listing['tags'],
            ],
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        $this->nextVersionId++;
    }

    private function updateListing(array &$listing, array $changes): void
    {
        foreach ($changes as $key => $value) {
            $listing[$key] = $value;
        }
        $listing['version']++;
        $this->createVersionSnapshot($listing);
    }

    private function diffVersions(int $v1Id, int $v2Id): array
    {
        $snap1 = $this->versions[$v1Id]['snapshot'] ?? [];
        $snap2 = $this->versions[$v2Id]['snapshot'] ?? [];

        $diff = [];
        $allKeys = array_unique(array_merge(array_keys($snap1), array_keys($snap2)));

        foreach ($allKeys as $key) {
            $old = $snap1[$key] ?? null;
            $new = $snap2[$key] ?? null;
            if ($old !== $new) {
                $diff[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $diff;
    }

    public function test_create_listing_creates_version_1(): void
    {
        $listing = $this->createListing([]);

        $this->assertEquals(1, $listing['version']);
        $this->assertCount(1, $this->versions);
        $this->assertEquals(1, $this->versions[1]['version']);
    }

    public function test_update_listing_increments_version(): void
    {
        $listing = $this->createListing([]);
        $this->assertEquals(1, $listing['version']);

        $this->updateListing($listing, ['title' => 'Updated Title']);
        $this->assertEquals(2, $listing['version']);

        $this->updateListing($listing, ['description' => 'Updated Desc']);
        $this->assertEquals(3, $listing['version']);
    }

    public function test_version_snapshot_contains_all_fields(): void
    {
        $listing = $this->createListing([
            'title'        => 'Test Title',
            'description'  => 'Test Description',
            'departure'    => '789 Start Rd',
            'destination'  => '012 End Blvd',
            'rider_count'  => 2,
            'vehicle_type' => 'suv',
            'tags'         => 'weekend',
        ]);

        $snapshot = $this->versions[1]['snapshot'];

        $this->assertEquals('Test Title', $snapshot['title']);
        $this->assertEquals('Test Description', $snapshot['description']);
        $this->assertEquals('789 Start Rd', $snapshot['departure']);
        $this->assertEquals('012 End Blvd', $snapshot['destination']);
        $this->assertEquals(2, $snapshot['rider_count']);
        $this->assertEquals('suv', $snapshot['vehicle_type']);
        $this->assertEquals('weekend', $snapshot['tags']);
    }

    public function test_diff_identifies_changed_fields(): void
    {
        $listing = $this->createListing(['title' => 'V1 Title', 'description' => 'Same Desc']);
        $this->updateListing($listing, ['title' => 'V2 Title']);

        $diff = $this->diffVersions(1, 2);

        $this->assertArrayHasKey('title', $diff);
        $this->assertEquals('V1 Title', $diff['title']['old']);
        $this->assertEquals('V2 Title', $diff['title']['new']);

        // Description unchanged, should not be in diff
        $this->assertArrayNotHasKey('description', $diff);
    }
}
