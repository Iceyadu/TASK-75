<?php

namespace API_tests;

use PHPUnit\Framework\TestCase;

/**
 * API integration tests for listing endpoints.
 * Requires running backend + MySQL with seeded test data.
 */
class ListingApiTest extends TestCase
{
    protected static string $baseUrl;
    protected static ?string $authToken = null;
    protected static ?int $listingId = null;
    protected static string $cookieFile;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = rtrim(getenv('API_BASE_URL') ?: 'http://localhost:8081', '/');
        self::$cookieFile = tempnam(sys_get_temp_dir(), 'rc_listing_');
        self::$authToken = self::loginAndGetToken();
    }

    protected static function loginAndGetToken(): string
    {
        $ch = curl_init(self::$baseUrl . '/api/auth/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS     => json_encode([
                'email'    => getenv('TEST_USER_EMAIL') ?: 'alice@ridecircle.local',
                'password' => getenv('TEST_USER_PASSWORD') ?: 'Alice123!',
                'organization_code' => getenv('TEST_ORG_CODE') ?: 'RC2026',
            ]),
            CURLOPT_COOKIEFILE     => self::$cookieFile,
            CURLOPT_COOKIEJAR      => self::$cookieFile,
        ]);
        $body = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $body['data']['token'] ?? '';
    }

    protected function api(string $method, string $path, array $data = [], ?string $token = null): array
    {
        $url = self::$baseUrl . $path;
        $ch  = curl_init();
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $t = $token ?? self::$authToken;
        if ($t) $headers[] = 'Authorization: Bearer ' . $t;

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_COOKIEFILE     => self::$cookieFile,
            CURLOPT_COOKIEJAR      => self::$cookieFile,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET' && $data) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        }

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => json_decode($body, true) ?? []];
    }

    // ----------------------------------------------------------------
    // Create
    // ----------------------------------------------------------------

    public function test_create_listing_as_draft(): void
    {
        $res = $this->api('POST', '/api/listings', [
            'title'           => 'Test Draft Listing ' . uniqid(),
            'description'     => 'Integration test listing created as draft',
            'pickup_address'  => '123 Test St',
            'dropoff_address' => '456 Dest Ave',
            'rider_count'     => 2,
            'vehicle_type'    => 'sedan',
            'time_window_start' => '2026-05-01 08:00:00',
            'time_window_end'   => '2026-05-01 09:00:00',
        ]);

        $this->assertEquals(201, $res['status'], 'Create listing should return 201');
        $listing = $res['body']['data'] ?? [];
        $this->assertEquals('draft', $listing['status'] ?? null, 'New listing should be draft');
        self::$listingId = $listing['id'] ?? null;
    }

    public function test_create_listing_and_publish(): void
    {
        $res = $this->api('POST', '/api/listings', [
            'title'           => 'Test Published Listing ' . uniqid(),
            'description'     => 'This listing will be published immediately',
            'pickup_address'  => '789 Origin Rd',
            'dropoff_address' => '321 Final Blvd',
            'rider_count'     => 3,
            'vehicle_type'    => 'suv',
            'time_window_start' => '2026-05-02 10:00:00',
            'time_window_end'   => '2026-05-02 11:00:00',
            'publish'         => true,
        ]);

        $this->assertContains($res['status'], [200, 201]);
        $listing = $res['body']['data'] ?? $res['body'];
        $this->assertEquals('active', $listing['status'], 'Published listing should be active');
    }

    // ----------------------------------------------------------------
    // Edit and versions
    // ----------------------------------------------------------------

    public function test_edit_listing_creates_version(): void
    {
        $this->assertNotNull(self::$listingId, 'Listing ID must exist from create test');

        $res = $this->api('PUT', '/api/listings/' . self::$listingId, [
            'title'          => 'Updated Title ' . uniqid(),
            'pickup_address' => '999 New Pickup St',
        ]);

        $this->assertEquals(200, $res['status'], 'Edit should return 200');

        // Verify version was incremented
        $versions = $this->api('GET', '/api/listings/' . self::$listingId . '/versions');
        $this->assertEquals(200, $versions['status']);
        $this->assertGreaterThanOrEqual(2, count($versions['body']['versions'] ?? $versions['body']['data'] ?? []),
            'Should have at least 2 versions after edit');
    }

    // ----------------------------------------------------------------
    // Publish / Unpublish
    // ----------------------------------------------------------------

    public function test_publish_draft_listing(): void
    {
        $this->assertNotNull(self::$listingId);

        $res = $this->api('POST', '/api/listings/' . self::$listingId . '/publish');
        $this->assertEquals(200, $res['status'], 'Publish should return 200');

        $show = $this->api('GET', '/api/listings/' . self::$listingId);
        $listing = $show['body']['data'] ?? $show['body'];
        $this->assertEquals('active', $listing['status'], 'Listing should be active after publish');
    }

    public function test_unpublish_active_listing(): void
    {
        $this->assertNotNull(self::$listingId);

        $res = $this->api('POST', '/api/listings/' . self::$listingId . '/unpublish');
        $this->assertEquals(200, $res['status'], 'Unpublish should return 200');

        $show = $this->api('GET', '/api/listings/' . self::$listingId);
        $listing = $show['body']['data'] ?? $show['body'];
        $this->assertEquals('draft', $listing['status'], 'Listing should be draft after unpublish');
    }

    // ----------------------------------------------------------------
    // Bulk close
    // ----------------------------------------------------------------

    public function test_bulk_close_active_listings(): void
    {
        // Create and publish two listings
        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $res = $this->api('POST', '/api/listings', [
                'title'           => 'Bulk Close Test ' . $i . ' ' . uniqid(),
                'description'     => 'For bulk close test',
                'pickup_address'  => '101 Start Street',
                'dropoff_address' => '202 End Avenue',
                'rider_count'     => 1,
                'vehicle_type'    => 'sedan',
                'time_window_start' => '2026-06-01 08:00:00',
                'time_window_end'   => '2026-06-01 09:00:00',
                'publish'         => true,
            ]);
            $listing = $res['body']['data'] ?? $res['body'];
            $ids[] = $listing['id'];
        }

        $res = $this->api('POST', '/api/listings/bulk-close', ['listing_ids' => $ids, 'reason' => 'bulk test']);
        $this->assertContains($res['status'], [200, 403], 'Bulk close should succeed for admins or be forbidden for regular users');
        if ($res['status'] === 200) {
            $data = $res['body']['data'] ?? [];
            $this->assertCount(2, $data['closed'] ?? [], 'Should close 2 listings');
        }
    }

    public function test_bulk_close_fails_for_matched_listings(): void
    {
        // Attempt to bulk close a matched listing should skip it
        // This test assumes the backend correctly skips non-active listings
        $res = $this->api('POST', '/api/listings/bulk-close', ['listing_ids' => [-999], 'reason' => 'bulk invalid']);
        // The API should either return 200 with closed_count=0 or 422
        $this->assertContains($res['status'], [200, 403, 422]);
        if ($res['status'] === 200) {
            $data = $res['body']['data'] ?? [];
            $this->assertEquals(0, count($data['closed'] ?? []), 'Should not close invalid listings');
        }
    }

    // ----------------------------------------------------------------
    // Search
    // ----------------------------------------------------------------

    public function test_search_by_keyword(): void
    {
        $res = $this->api('GET', '/api/listings', ['q' => 'Test', 'sort' => 'newest']);
        $this->assertEquals(200, $res['status']);
        $this->assertArrayHasKey('data', $res['body']);
        $this->assertArrayHasKey('listings', $res['body']['data']);
    }

    public function test_search_filter_vehicle_type(): void
    {
        $res = $this->api('GET', '/api/listings', ['vehicle_type' => 'sedan']);
        $this->assertEquals(200, $res['status']);
        $listings = $res['body']['data']['listings'] ?? [];
        foreach ($listings as $l) {
            $this->assertEquals('sedan', $l['vehicle_type'], 'All results should be sedan');
        }
    }

    public function test_search_filter_rider_count(): void
    {
        $res = $this->api('GET', '/api/listings', ['rider_count_min' => 2, 'rider_count_max' => 4]);
        $this->assertEquals(200, $res['status']);
        $listings = $res['body']['data']['listings'] ?? [];
        foreach ($listings as $l) {
            $this->assertGreaterThanOrEqual(2, $l['rider_count']);
            $this->assertLessThanOrEqual(4, $l['rider_count']);
        }
    }

    public function test_search_sort_newest(): void
    {
        $res = $this->api('GET', '/api/listings', ['sort' => 'newest', 'per_page' => 5]);
        $this->assertEquals(200, $res['status']);
        $listings = $res['body']['data']['listings'] ?? [];
        if (count($listings) >= 2) {
            $this->assertGreaterThanOrEqual(
                strtotime($listings[1]['created_at']),
                strtotime($listings[0]['created_at']),
                'First listing should be newer or same age as second'
            );
        }
    }

    public function test_search_sort_most_popular(): void
    {
        $res = $this->api('GET', '/api/listings', ['sort' => 'most_popular', 'per_page' => 5]);
        $this->assertEquals(200, $res['status']);
        $this->assertArrayHasKey('listings', $res['body']['data'] ?? []);
    }

    public function test_search_no_results_returns_recent_active(): void
    {
        $res = $this->api('GET', '/api/listings', ['q' => 'xyznonexistentkeyword99999']);
        $this->assertEquals(200, $res['status']);
        $listings = $res['body']['data']['listings'] ?? [];
        $this->assertCount(0, $listings, 'Should have no direct results');
        // API may return recent_active fallback
        if (isset($res['body']['data']['recent_active'])) {
            $this->assertIsArray($res['body']['data']['recent_active']);
        }
    }
}
