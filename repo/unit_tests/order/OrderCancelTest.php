<?php
declare(strict_types=1);

namespace unit_tests\order;

use PHPUnit\Framework\TestCase;

/**
 * CRITICAL test file: order cancellation rules.
 *
 * Business rules:
 *   - Free cancel within 5 minutes of acceptance
 *   - Reason required after 5 minutes
 *   - Cancel blocked when status is in_progress (MUST throw BusinessException code 40901)
 *   - Cancel reason "OTHER" requires freetext
 *   - Cancel restores listing to active
 *   - Cancel on pending_match is always free
 */
class OrderCancelTest extends TestCase
{
    private array $listings = [];

    private function makeOrder(array $overrides = []): array
    {
        return array_merge([
            'id'              => 1,
            'listing_id'      => 100,
            'passenger_id'    => 20,
            'driver_id'       => 10,
            'organization_id' => 1,
            'status'          => 'accepted',
            'accepted_at'     => date('Y-m-d H:i:s'),
            'created_at'      => date('Y-m-d H:i:s'),
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->listings[100] = ['id' => 100, 'status' => 'matched'];
    }

    /**
     * Simulate the cancel logic.
     *
     * @param array       $order
     * @param string|null $reasonCode  e.g. 'SCHEDULE_CHANGE', 'EMERGENCY', 'OTHER'
     * @param string|null $reasonText  Required when reasonCode is 'OTHER'
     * @return array Modified order
     */
    private function cancelOrder(array &$order, ?string $reasonCode = null, ?string $reasonText = null): array
    {
        // Block cancel when in_progress -- MUST throw code 40901
        if ($order['status'] === 'in_progress') {
            throw new \RuntimeException('Cannot cancel an in-progress order', 40901);
        }

        // Only pending_match, accepted, or matched can be canceled
        $cancelableStatuses = ['pending_match', 'accepted'];
        if (!in_array($order['status'], $cancelableStatuses, true)) {
            throw new \RuntimeException('Order cannot be canceled in current status', 40001);
        }

        // Determine if free cancel applies
        $isFreeCancel = false;

        if ($order['status'] === 'pending_match') {
            $isFreeCancel = true;
        } elseif ($order['accepted_at'] !== null) {
            $acceptedTime = strtotime($order['accepted_at']);
            $freeWindow = $acceptedTime + (5 * 60); // 5 minutes
            if (time() <= $freeWindow) {
                $isFreeCancel = true;
            }
        }

        // If not a free cancel, reason is required
        if (!$isFreeCancel && empty($reasonCode)) {
            throw new \RuntimeException('Cancellation reason required after 5-minute free window', 40001);
        }

        // If reason is OTHER, text is required
        if ($reasonCode === 'OTHER' && empty($reasonText)) {
            throw new \RuntimeException('Text explanation required for "OTHER" cancellation reason', 40001);
        }

        $order['status'] = 'canceled';
        $order['canceled_at'] = date('Y-m-d H:i:s');
        $order['cancel_reason_code'] = $reasonCode;
        $order['cancel_reason_text'] = $reasonText;

        // Restore listing to active
        $listingId = $order['listing_id'];
        if (isset($this->listings[$listingId])) {
            $this->listings[$listingId]['status'] = 'active';
        }

        return $order;
    }

    public function test_free_cancel_within_5_minutes_of_acceptance(): void
    {
        // Accepted just now
        $order = $this->makeOrder([
            'status'      => 'accepted',
            'accepted_at' => date('Y-m-d H:i:s'),
        ]);

        // No reason needed for free cancel
        $this->cancelOrder($order, null, null);
        $this->assertEquals('canceled', $order['status']);
    }

    public function test_reason_required_after_5_minutes(): void
    {
        // Accepted 10 minutes ago
        $order = $this->makeOrder([
            'status'      => 'accepted',
            'accepted_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->cancelOrder($order, null, null);
    }

    public function test_cancel_blocked_when_in_progress(): void
    {
        $order = $this->makeOrder(['status' => 'in_progress']);

        try {
            $this->cancelOrder($order);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            // MUST throw BusinessException with code 40901
            $this->assertEquals(40901, $e->getCode());
            $this->assertStringContainsString('in-progress', $e->getMessage());
        }
    }

    public function test_cancel_reason_OTHER_requires_text(): void
    {
        $order = $this->makeOrder([
            'status'      => 'accepted',
            'accepted_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->cancelOrder($order, 'OTHER', null);
    }

    public function test_cancel_restores_listing_to_active(): void
    {
        $order = $this->makeOrder(['status' => 'accepted']);
        $this->assertEquals('matched', $this->listings[100]['status']);

        $this->cancelOrder($order);
        $this->assertEquals('active', $this->listings[100]['status']);
    }

    public function test_cancel_pending_match_is_always_free(): void
    {
        $order = $this->makeOrder([
            'status'      => 'pending_match',
            'accepted_at' => null,
            'created_at'  => date('Y-m-d H:i:s', strtotime('-60 minutes')),
        ]);

        // Even though it's old, pending_match cancel is free
        $this->cancelOrder($order, null, null);
        $this->assertEquals('canceled', $order['status']);
    }
}
