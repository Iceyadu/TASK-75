<?php
declare(strict_types=1);

namespace unit_tests\listing;

use PHPUnit\Framework\TestCase;

/**
 * Tests for search logic.
 *
 * Simulates the SearchService's keyword matching, filtering, sorting,
 * highlighting, and did-you-mean features without a database.
 */
class SearchTest extends TestCase
{
    /** Simulated listings dataset */
    private array $listings = [];

    /** Simulated search dictionary */
    private array $dictionary = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->listings = [
            [
                'id'           => 1,
                'title'        => 'Morning Commute Downtown',
                'description'  => 'Daily ride from suburbs to city center',
                'tags'         => 'commute,morning,daily',
                'vehicle_type' => 'sedan',
                'rider_count'  => 3,
                'status'       => 'active',
                'view_count'   => 100,
                'created_at'   => '2026-04-01 08:00:00',
            ],
            [
                'id'           => 2,
                'title'        => 'Weekend Trip to Beach',
                'description'  => 'Fun trip to the coast, room for luggage',
                'tags'         => 'weekend,beach,fun',
                'vehicle_type' => 'suv',
                'rider_count'  => 5,
                'status'       => 'active',
                'view_count'   => 50,
                'created_at'   => '2026-04-05 10:00:00',
            ],
            [
                'id'           => 3,
                'title'        => 'Airport Shuttle Service',
                'description'  => 'Reliable airport transportation with large vehicle',
                'tags'         => 'airport,shuttle,reliable',
                'vehicle_type' => 'van',
                'rider_count'  => 8,
                'status'       => 'active',
                'view_count'   => 200,
                'created_at'   => '2026-04-03 14:00:00',
            ],
        ];

        $this->dictionary = ['commute', 'morning', 'weekend', 'beach', 'airport', 'shuttle', 'downtown'];
    }

    private function search(string $keyword): array
    {
        $keyword = mb_strtolower($keyword);
        $results = [];

        foreach ($this->listings as $listing) {
            if ($listing['status'] !== 'active') {
                continue;
            }
            $haystack = mb_strtolower(
                $listing['title'] . ' ' . $listing['description'] . ' ' . $listing['tags']
            );
            if (strpos($haystack, $keyword) !== false) {
                $results[] = $listing;
            }
        }

        return $results;
    }

    private function filterByVehicleType(array $listings, string $type): array
    {
        return array_values(array_filter($listings, fn($l) => $l['vehicle_type'] === $type));
    }

    private function filterByRiderCountRange(array $listings, int $min, int $max): array
    {
        return array_values(array_filter($listings, fn($l) => $l['rider_count'] >= $min && $l['rider_count'] <= $max));
    }

    private function sortByNewest(array $listings): array
    {
        usort($listings, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        return $listings;
    }

    private function sortByMostPopular(array $listings): array
    {
        usort($listings, fn($a, $b) => $b['view_count'] - $a['view_count']);
        return $listings;
    }

    private function highlight(string $text, string $term): string
    {
        return preg_replace(
            '/(' . preg_quote($term, '/') . ')/i',
            '<mark>$1</mark>',
            $text
        );
    }

    private function didYouMean(string $input): ?string
    {
        $input = mb_strtolower($input);
        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($this->dictionary as $word) {
            $distance = levenshtein($input, $word);
            if ($distance < $bestDistance && $distance <= 2 && $distance > 0) {
                $bestDistance = $distance;
                $bestMatch = $word;
            }
        }

        return $bestMatch;
    }

    public function test_keyword_search_matches_title(): void
    {
        $results = $this->search('commute');
        $this->assertCount(1, $results);
        $this->assertEquals(1, $results[0]['id']);
    }

    public function test_keyword_search_matches_description(): void
    {
        $results = $this->search('luggage');
        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]['id']);
    }

    public function test_keyword_search_matches_tags(): void
    {
        $results = $this->search('shuttle');
        $this->assertCount(1, $results);
        $this->assertEquals(3, $results[0]['id']);
    }

    public function test_filter_by_vehicle_type(): void
    {
        $filtered = $this->filterByVehicleType($this->listings, 'suv');
        $this->assertCount(1, $filtered);
        $this->assertEquals(2, $filtered[0]['id']);
    }

    public function test_filter_by_rider_count_range(): void
    {
        $filtered = $this->filterByRiderCountRange($this->listings, 4, 10);
        $this->assertCount(2, $filtered);
        $ids = array_column($filtered, 'id');
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
    }

    public function test_sort_by_newest(): void
    {
        $sorted = $this->sortByNewest($this->listings);
        $this->assertEquals(2, $sorted[0]['id']); // April 5
        $this->assertEquals(3, $sorted[1]['id']); // April 3
        $this->assertEquals(1, $sorted[2]['id']); // April 1
    }

    public function test_sort_by_most_popular(): void
    {
        $sorted = $this->sortByMostPopular($this->listings);
        $this->assertEquals(3, $sorted[0]['id']); // 200 views
        $this->assertEquals(1, $sorted[1]['id']); // 100 views
        $this->assertEquals(2, $sorted[2]['id']); // 50 views
    }

    public function test_highlight_wraps_matched_terms(): void
    {
        $highlighted = $this->highlight('Morning Commute Downtown', 'commute');
        $this->assertStringContainsString('<mark>Commute</mark>', $highlighted);
    }

    public function test_no_results_returns_recent_active(): void
    {
        $results = $this->search('xyznonexistent');
        $this->assertCount(0, $results);

        // Fallback: return recent active listings
        $fallback = $this->sortByNewest(array_filter($this->listings, fn($l) => $l['status'] === 'active'));
        $this->assertNotEmpty($fallback);
        $this->assertEquals(2, $fallback[0]['id']);
    }

    public function test_did_you_mean_for_typo(): void
    {
        // "comute" is a typo for "commute" (Levenshtein distance = 1)
        $suggestion = $this->didYouMean('comute');
        $this->assertEquals('commute', $suggestion);

        // "airprt" is a typo for "airport" (Levenshtein distance = 1)
        $suggestion = $this->didYouMean('airprt');
        $this->assertEquals('airport', $suggestion);

        // Exact match should return null (distance = 0)
        $suggestion = $this->didYouMean('beach');
        $this->assertNull($suggestion);
    }
}
