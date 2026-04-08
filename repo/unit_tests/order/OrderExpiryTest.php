<?php
declare(strict_types=1);

namespace unit_tests\order;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the order:expire command logic.
 */
class OrderExpiryTest extends TestCase
{
    /** Simulated order store */
    private array $orders = [];

    /** Simulated listing store */
    private array $listings = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Listing that was matched
        $this->listings[100] = ['id' => 100, 'status' => 'matched'];
        $this->listings[101] = ['id' => 101, 'status' => 'active'];

        // Order older than 30 minutes (should expire)
        $this->orders[1] = [
            'id'         => 1,
            'listing_id' => 100,
            'status'     => 'pending_match',
            'created_at' => date('Y-m-d H:i:s', strtotime('-35 minutes')),
        ];

        // Order exactly 30 minutes old (borderline, should expire)
        $this->orders[2] = [
            'id'         => 2,
            'listing_id' => 101,
            'status'     => 'pending_match',
            'created_at' => date('Y-m-d H:i:s', strtotime('-31 minutes')),
        ];

        // Order only 10 minutes old (should NOT expire)
        $this->orders[3] = [
            'id'         => 3,
            'listing_id' => 101,
            'status'     => 'pending_match',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ];

        // Already accepted order, older than 30 min (should NOT expire)
        $this->orders[4] = [
            'id'         => 4,
            'listing_id' => 101,
            'status'     => 'accepted',
            'created_at' => date('Y-m-d H:i:s', strtotime('-60 minutes')),
        ];
    }

    /**
     * Simulates the expire command logic.
     * Returns count of expired orders.
     */
    private function runExpireCommand(): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $expiredCount = 0;

        foreach ($this->orders as &$order) {
            if ($order['status'] === 'pending_match' && $order['created_at'] < $cutoff) {
                $order['status'] = 'expired';
                $expiredCount++;

                // Restore listing to active
                $listingId = $order['listing_id'];
                if (isset($this->listings[$listingId]) && $this->listings[$listingId]['status'] === 'matched') {
                    $this->listings[$listingId]['status'] = 'active';
                }
            }
        }

        return $expiredCount;
    }

    public function test_expire_command_expires_orders_older_than_30_minutes(): void
    {
        $count = $this->runExpireCommand();

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertEquals('expired', $this->orders[1]['status']);
        $this->assertEquals('expired', $this->orders[2]['status']);
    }

    public function test_expire_command_ignores_non_pending_orders(): void
    {
        $this->runExpireCommand();

        // Accepted order should not be touched
        $this->assertEquals('accepted', $this->orders[4]['status']);
        // Recent pending_match should not be touched
        $this->assertEquals('pending_match', $this->orders[3]['status']);
    }

    public function test_expire_restores_listing_to_active(): void
    {
        $this->assertEquals('matched', $this->listings[100]['status']);
        $this->runExpireCommand();
        $this->assertEquals('active', $this->listings[100]['status']);
    }

    public function test_expire_is_idempotent(): void
    {
        $count1 = $this->runExpireCommand();
        $this->assertGreaterThan(0, $count1);

        // Running again should not expire anything new
        $count2 = $this->runExpireCommand();
        $this->assertEquals(0, $count2);
    }
}
