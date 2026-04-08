<?php
declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\exception\ValidationException;
use app\model\Listing;
use app\model\Order;
use think\facade\Db;

class OrderService
{
    protected AuditService $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    /**
     * Create a new order for a listing (driver accepts a ride request).
     *
     * When a driver accepts a listing, the order is created directly in
     * 'accepted' status per the prompt's "driver accepts request" semantics.
     */
    public function create(int $orgId, int $driverId, array $data): Order
    {
        $listingId = (int) $data['listing_id'];

        $listing = Listing::where('id', $listingId)
            ->where('organization_id', $orgId)
            ->find();

        if (!$listing) {
            throw new NotFoundException('Listing not found');
        }

        if ($listing->status !== 'active') {
            throw new BusinessException('Listing is not active', 40901, 409);
        }

        if ((int) $listing->user_id === $driverId) {
            throw new BusinessException('Driver cannot be the listing owner', 40901, 409);
        }

        // Check for existing pending/active order on this listing
        $existingOrder = Order::where('listing_id', $listingId)
            ->whereIn('status', ['pending_match', 'accepted', 'in_progress'])
            ->find();

        if ($existingOrder) {
            throw new BusinessException('An active order already exists for this listing', 40901, 409);
        }

        $now = date('Y-m-d H:i:s');

        $order = new Order();
        $order->organization_id = $orgId;
        $order->listing_id     = $listingId;
        $order->driver_id      = $driverId;
        $order->passenger_id   = (int) $listing->user_id;
        $order->status         = 'accepted';
        $order->accepted_at    = $now;
        $order->driver_notes   = $data['driver_notes'] ?? '';
        $order->save();

        // Update listing status
        $listing->status = 'matched';
        $listing->save();

        // Record audit log
        $this->auditService->log(
            $orgId,
            $driverId,
            'order.create',
            'order',
            $order->id,
            null,
            ['status' => 'accepted', 'listing_id' => $listingId]
        );

        return $order;
    }

    /**
     * Accept a pending order (passenger/listing owner only).
     */
    public function accept(int $orderId, int $userId): Order
    {
        $order = $this->findOrderOrFail($orderId);

        if ((int) $order->passenger_id !== $userId) {
            throw new ForbiddenException('Only the passenger (listing owner) can accept this order');
        }

        if ($order->status !== 'pending_match') {
            throw new BusinessException(
                'Order must be in pending_match status to accept',
                40901,
                409
            );
        }

        $oldStatus = $order->status;
        $order->status      = 'accepted';
        $order->accepted_at = date('Y-m-d H:i:s');
        $order->save();

        $this->auditService->log(
            (int) $order->organization_id,
            $userId,
            'order.accept',
            'order',
            $order->id,
            ['status' => $oldStatus],
            ['status' => 'accepted']
        );

        return $order;
    }

    /**
     * Start an accepted order (must be party).
     */
    public function start(int $orderId, int $userId): Order
    {
        $order = $this->findOrderOrFail($orderId);
        $this->verifyParty($order, $userId);

        if ($order->status !== 'accepted') {
            throw new BusinessException(
                'Order must be in accepted status to start',
                40901,
                409
            );
        }

        $oldStatus = $order->status;
        $order->status     = 'in_progress';
        $order->started_at = date('Y-m-d H:i:s');
        $order->save();

        // Update listing status
        $listing = Listing::find($order->listing_id);
        if ($listing) {
            $listing->status = 'in_progress';
            $listing->save();
        }

        $this->auditService->log(
            (int) $order->organization_id,
            $userId,
            'order.start',
            'order',
            $order->id,
            ['status' => $oldStatus],
            ['status' => 'in_progress']
        );

        return $order;
    }

    /**
     * Complete an in-progress order (must be party).
     */
    public function complete(int $orderId, int $userId): Order
    {
        $order = $this->findOrderOrFail($orderId);
        $this->verifyParty($order, $userId);

        if ($order->status !== 'in_progress') {
            throw new BusinessException(
                'Order must be in progress to complete',
                40901,
                409
            );
        }

        $oldStatus = $order->status;
        $order->status       = 'completed';
        $order->completed_at = date('Y-m-d H:i:s');
        $order->save();

        // Update listing status
        $listing = Listing::find($order->listing_id);
        if ($listing) {
            $listing->status = 'completed';
            $listing->save();
        }

        $this->auditService->log(
            (int) $order->organization_id,
            $userId,
            'order.complete',
            'order',
            $order->id,
            ['status' => $oldStatus],
            ['status' => 'completed']
        );

        return $order;
    }

    /**
     * Cancel an order with strict business rules.
     */
    public function cancel(int $orderId, int $userId, ?string $reasonCode, ?string $reasonText): Order
    {
        $order = $this->findOrderOrFail($orderId);
        $this->verifyParty($order, $userId);

        // Rule 2: Cannot cancel in_progress
        if ($order->status === 'in_progress') {
            throw new BusinessException(
                'Cancellation is not allowed once a trip is in progress.',
                40901,
                409
            );
        }

        // Must be in a cancellable state
        if (!in_array($order->status, ['pending_match', 'accepted'])) {
            throw new BusinessException(
                'Order cannot be cancelled in its current status',
                40901,
                409
            );
        }

        // Rule 4: Accepted order cancellation rules
        if ($order->status === 'accepted') {
            $acceptedAt       = strtotime($order->accepted_at);
            $fiveMinutesLater = $acceptedAt + (5 * 60);
            $now              = time();

            if ($now > $fiveMinutesLater) {
                // Past 5 minutes: reason_code required
                if (empty($reasonCode)) {
                    throw new ValidationException(
                        'Cancellation reason is required after the free cancellation window',
                        ['reason_code' => 'Reason code is required for late cancellations']
                    );
                }

                // If OTHER, reason_text is required
                if ($reasonCode === 'OTHER' && empty($reasonText)) {
                    throw new ValidationException(
                        'Reason text is required when reason code is OTHER',
                        ['reason_text' => 'Please provide details for your cancellation reason']
                    );
                }
            }
            // Within 5 minutes: free cancel, no reason required
        }

        // Rule 5: Apply cancellation
        $oldStatus = $order->status;
        $order->status             = 'canceled';
        $order->canceled_at        = date('Y-m-d H:i:s');
        $order->cancel_reason_code = $reasonCode;
        $order->cancel_reason_text = $reasonText;
        $order->save();

        // Restore listing to active
        $listing = Listing::find($order->listing_id);
        if ($listing) {
            $listing->status = 'active';
            $listing->save();
        }

        // Rule 6: Record audit log
        $this->auditService->log(
            (int) $order->organization_id,
            $userId,
            'order.cancel',
            'order',
            $order->id,
            ['status' => $oldStatus],
            ['status' => 'canceled', 'reason_code' => $reasonCode, 'reason_text' => $reasonText]
        );

        return $order;
    }

    /**
     * Dispute a completed order (within 72 hours of completion).
     */
    public function dispute(int $orderId, int $userId, string $reason): Order
    {
        $order = $this->findOrderOrFail($orderId);
        $this->verifyParty($order, $userId);

        if ($order->status !== 'completed') {
            throw new BusinessException(
                'Only completed orders can be disputed',
                40901,
                409
            );
        }

        $completedAt     = strtotime($order->completed_at);
        $seventyTwoHours = $completedAt + (72 * 3600);
        if (time() > $seventyTwoHours) {
            throw new BusinessException(
                'Dispute window has expired (72 hours after completion)',
                40901,
                409
            );
        }

        $oldStatus = $order->status;
        $order->status         = 'disputed';
        $order->disputed_at    = date('Y-m-d H:i:s');
        $order->dispute_reason = $reason;
        $order->save();

        // Update listing status
        $listing = Listing::find($order->listing_id);
        if ($listing) {
            $listing->status = 'disputed';
            $listing->save();
        }

        $this->auditService->log(
            (int) $order->organization_id,
            $userId,
            'order.dispute',
            'order',
            $order->id,
            ['status' => $oldStatus],
            ['status' => 'disputed', 'reason' => $reason]
        );

        return $order;
    }

    /**
     * Resolve a disputed order (admin only, checked at route level).
     */
    public function resolve(int $orderId, string $resolution, string $outcome): Order
    {
        $order = $this->findOrderOrFail($orderId);

        if ($order->status !== 'disputed') {
            throw new BusinessException(
                'Only disputed orders can be resolved',
                40901,
                409
            );
        }

        $oldStatus = $order->status;
        $order->status             = 'resolved';
        $order->resolved_at        = date('Y-m-d H:i:s');
        $order->resolution_notes   = $resolution;
        $order->resolution_outcome = $outcome;
        $order->save();

        // Update listing status
        $listing = Listing::find($order->listing_id);
        if ($listing) {
            $listing->status = 'resolved';
            $listing->save();
        }

        $this->auditService->log(
            (int) $order->organization_id,
            (int) request()->user->id,
            'order.resolve',
            'order',
            $order->id,
            ['status' => $oldStatus],
            ['status' => 'resolved', 'outcome' => $outcome]
        );

        return $order;
    }

    /**
     * Expire all pending orders that have passed their 30-minute window.
     */
    public function expirePendingOrders(): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));

        $expiredOrders = Order::where('status', 'pending_match')
            ->where('created_at', '<', $cutoff)
            ->select();

        $count = 0;
        foreach ($expiredOrders as $order) {
            $order->status = 'expired';
            $order->save();

            // Restore listing to active
            $listing = Listing::find($order->listing_id);
            if ($listing && $listing->status === 'matched') {
                $listing->status = 'active';
                $listing->save();
            }

            $count++;
        }

        return $count;
    }

    // ---- Private helpers ----

    private function findOrderOrFail(int $orderId): Order
    {
        $order = Order::find($orderId);
        if (!$order) {
            throw new NotFoundException('Order not found');
        }
        return $order;
    }

    private function verifyParty(Order $order, int $userId): void
    {
        if ((int) $order->passenger_id !== $userId && (int) $order->driver_id !== $userId) {
            throw new ForbiddenException('You are not a party to this order');
        }
    }
}
