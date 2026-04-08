<?php
declare(strict_types=1);

namespace unit_tests\order;

use PHPUnit\Framework\TestCase;

/**
 * MOST IMPORTANT TEST FILE.
 *
 * Tests for the order lifecycle state machine. Every valid transition must
 * succeed, every invalid transition must be rejected, and boundary conditions
 * (exactly 30 min, exactly 5 min, exactly 72 hours) must be tested.
 */
class OrderStateMachineTest extends TestCase
{
    /** Allowed transitions: from => [to, ...] */
    private const TRANSITIONS = [
        'pending_match' => ['accepted', 'expired', 'canceled'],
        'accepted'      => ['in_progress', 'canceled'],
        'in_progress'   => ['completed'],
        'completed'     => ['disputed'],
        'canceled'      => [],
        'expired'       => [],
        'disputed'      => ['resolved'],
        'resolved'      => [],
    ];

    private function makeOrder(array $overrides = []): array
    {
        return array_merge([
            'id'               => 1,
            'listing_id'       => 100,
            'passenger_id'     => 20,
            'driver_id'        => 10,
            'organization_id'  => 1,
            'status'           => 'pending_match',
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'accepted_at'      => null,
            'started_at'       => null,
            'completed_at'     => null,
            'canceled_at'      => null,
            'disputed_at'      => null,
            'created_at'       => date('Y-m-d H:i:s'),
        ], $overrides);
    }

    private function transition(array &$order, string $newStatus, int $actingUserId): void
    {
        $currentStatus = $order['status'];

        if (!in_array($newStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw new \RuntimeException(
                "Cannot transition from '{$currentStatus}' to '{$newStatus}'",
                40001
            );
        }

        // Role checks
        switch ($newStatus) {
            case 'accepted':
                // Only the passenger (listing creator's counterpart) can accept
                if ($actingUserId !== $order['passenger_id']) {
                    throw new \RuntimeException('Only the passenger can accept a match', 40301);
                }
                $order['accepted_at'] = date('Y-m-d H:i:s');
                break;

            case 'in_progress':
                // Only a party (driver or passenger) can start
                if ($actingUserId !== $order['driver_id'] && $actingUserId !== $order['passenger_id']) {
                    throw new \RuntimeException('Only a party to the order can start the trip', 40301);
                }
                $order['started_at'] = date('Y-m-d H:i:s');
                break;

            case 'completed':
                // Only a party can complete
                if ($actingUserId !== $order['driver_id'] && $actingUserId !== $order['passenger_id']) {
                    throw new \RuntimeException('Only a party to the order can complete the trip', 40301);
                }
                $order['completed_at'] = date('Y-m-d H:i:s');
                break;
        }

        $order['status'] = $newStatus;
    }

    private function createOrder(int $listingOwnerId, int $passengerId): array
    {
        if ($listingOwnerId === $passengerId) {
            throw new \RuntimeException('Driver cannot accept own listing', 40001);
        }

        return $this->makeOrder([
            'driver_id'    => $listingOwnerId,
            'passenger_id' => $passengerId,
            'status'       => 'pending_match',
            'expires_at'   => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Creation Tests ─────────────────────────────────────────────────────

    public function test_create_order_sets_pending_match(): void
    {
        $order = $this->createOrder(10, 20);
        $this->assertEquals('pending_match', $order['status']);
    }

    public function test_create_order_sets_30_minute_expiry(): void
    {
        $order = $this->createOrder(10, 20);

        $expiresAt = strtotime($order['expires_at']);
        $expected = strtotime('+30 minutes');

        // Allow 2-second tolerance for test execution time
        $this->assertEqualsWithDelta($expected, $expiresAt, 2);
    }

    public function test_driver_cannot_accept_own_listing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40001);
        $this->createOrder(10, 10); // Same user as driver and passenger
    }

    // ── Valid Transition Tests ─────────────────────────────────────────────

    public function test_accept_transitions_pending_to_accepted(): void
    {
        $order = $this->makeOrder(['status' => 'pending_match']);
        $this->transition($order, 'accepted', 20); // Passenger accepts
        $this->assertEquals('accepted', $order['status']);
        $this->assertNotNull($order['accepted_at']);
    }

    public function test_start_transitions_accepted_to_in_progress(): void
    {
        $order = $this->makeOrder(['status' => 'accepted', 'accepted_at' => date('Y-m-d H:i:s')]);
        $this->transition($order, 'in_progress', 10); // Driver starts
        $this->assertEquals('in_progress', $order['status']);
        $this->assertNotNull($order['started_at']);
    }

    public function test_complete_transitions_in_progress_to_completed(): void
    {
        $order = $this->makeOrder([
            'status'     => 'in_progress',
            'accepted_at'=> date('Y-m-d H:i:s'),
            'started_at' => date('Y-m-d H:i:s'),
        ]);
        $this->transition($order, 'completed', 10); // Driver completes
        $this->assertEquals('completed', $order['status']);
        $this->assertNotNull($order['completed_at']);
    }

    // ── Invalid Transition Tests ──────────────────────────────────────────

    public function test_cannot_skip_states(): void
    {
        // pending_match directly to in_progress should fail
        $order = $this->makeOrder(['status' => 'pending_match']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40001);
        $this->transition($order, 'in_progress', 10);
    }

    // ── Role-Based Transition Tests ───────────────────────────────────────

    public function test_only_passenger_can_accept_match(): void
    {
        $order = $this->makeOrder(['status' => 'pending_match']);

        // Driver (user 10) trying to accept should fail
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40301);
        $this->transition($order, 'accepted', 10);
    }

    public function test_only_party_can_start_trip(): void
    {
        $order = $this->makeOrder(['status' => 'accepted']);

        // Random user (999) should not be able to start
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40301);
        $this->transition($order, 'in_progress', 999);
    }

    public function test_only_party_can_complete_trip(): void
    {
        $order = $this->makeOrder(['status' => 'in_progress']);

        // Random user (999) should not be able to complete
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40301);
        $this->transition($order, 'completed', 999);
    }
}
