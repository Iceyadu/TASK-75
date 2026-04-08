<?php
declare(strict_types=1);

namespace unit_tests\order;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the order dispute workflow.
 *
 * Business rules:
 *   - Dispute must be opened within 72 hours of completion
 *   - Only completed orders can be disputed
 *   - Only admin can resolve a dispute
 */
class OrderDisputeTest extends TestCase
{
    private function makeOrder(array $overrides = []): array
    {
        return array_merge([
            'id'              => 1,
            'status'          => 'completed',
            'completed_at'    => date('Y-m-d H:i:s'),
            'passenger_id'    => 20,
            'driver_id'       => 10,
            'organization_id' => 1,
        ], $overrides);
    }

    /**
     * Simulates opening a dispute.
     */
    private function openDispute(array &$order, string $reason, int $now = null): void
    {
        $now = $now ?? time();

        if ($order['status'] !== 'completed') {
            throw new \RuntimeException('Disputes can only be opened on completed orders', 40001);
        }

        $completedAt = strtotime($order['completed_at']);
        $windowEnd = $completedAt + (72 * 3600); // 72 hours

        if ($now > $windowEnd) {
            throw new \RuntimeException('Dispute window (72 hours) has expired', 40001);
        }

        $order['status'] = 'disputed';
        $order['disputed_at'] = date('Y-m-d H:i:s', $now);
        $order['dispute_reason'] = $reason;
    }

    /**
     * Simulates resolving a dispute.
     */
    private function resolveDispute(array &$order, string $outcome, string $notes, string $userRole): void
    {
        if ($order['status'] !== 'disputed') {
            throw new \RuntimeException('Order is not in disputed status', 40001);
        }

        if ($userRole !== 'administrator') {
            throw new \RuntimeException('Only administrators can resolve disputes', 40301);
        }

        $order['status'] = 'resolved';
        $order['dispute_outcome'] = $outcome;
        $order['dispute_notes'] = $notes;
        $order['resolved_at'] = date('Y-m-d H:i:s');
    }

    public function test_dispute_within_72_hours_succeeds(): void
    {
        $order = $this->makeOrder([
            'completed_at' => date('Y-m-d H:i:s', strtotime('-24 hours')),
        ]);

        $this->openDispute($order, 'Driver was rude');

        $this->assertEquals('disputed', $order['status']);
        $this->assertNotNull($order['disputed_at']);
    }

    public function test_dispute_after_72_hours_throws_exception(): void
    {
        $completedAt = strtotime('-73 hours');
        $order = $this->makeOrder([
            'completed_at' => date('Y-m-d H:i:s', $completedAt),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->openDispute($order, 'Too late', time());
    }

    public function test_dispute_only_on_completed_orders(): void
    {
        $order = $this->makeOrder(['status' => 'in_progress']);

        $this->expectException(\RuntimeException::class);
        $this->openDispute($order, 'Cannot dispute yet');
    }

    public function test_only_admin_can_resolve_dispute(): void
    {
        $order = $this->makeOrder(['status' => 'disputed']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(40301);
        $this->resolveDispute($order, 'in_favor_of_passenger', 'Driver at fault', 'regular_user');
    }

    public function test_resolve_sets_outcome_and_notes(): void
    {
        $order = $this->makeOrder(['status' => 'disputed']);

        $this->resolveDispute($order, 'in_favor_of_driver', 'Passenger no-show', 'administrator');

        $this->assertEquals('resolved', $order['status']);
        $this->assertEquals('in_favor_of_driver', $order['dispute_outcome']);
        $this->assertEquals('Passenger no-show', $order['dispute_notes']);
        $this->assertNotNull($order['resolved_at']);
    }
}
