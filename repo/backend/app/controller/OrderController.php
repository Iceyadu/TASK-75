<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ForbiddenException;
use app\exception\NotFoundException;
use app\model\Order;
use app\service\OrderService;

class OrderController extends BaseController
{
    protected OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    /**
     * GET /api/orders
     */
    public function index()
    {
        $userId  = (int) $this->request->user->id;
        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = min(100, max(1, (int) $this->request->get('per_page', 15)));
        $role    = $this->request->get('role', '');
        $status  = $this->request->get('status', '');

        $query = Order::where('organization_id', $this->request->orgId);

        // Filter by role
        if ($role === 'passenger') {
            $query->where('passenger_id', $userId);
        } elseif ($role === 'driver') {
            $query->where('driver_id', $userId);
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('passenger_id', $userId)
                  ->whereOr('driver_id', $userId);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $query->order('created_at', 'desc');

        $total    = $query->count();
        $orders   = $query->page($page, $perPage)->select();
        $lastPage = (int) ceil($total / $perPage);

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $orders->toArray(),
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ], 200);
    }

    /**
     * POST /api/orders
     */
    public function store()
    {
        validate($this->request->post(), 'app\validate\OrderValidate.create');

        $order = $this->orderService->create(
            $this->request->orgId,
            (int) $this->request->user->id,
            $this->request->post()
        );

        return json([
            'code'    => 0,
            'message' => 'Order created successfully',
            'data'    => $order->toArray(),
        ], 201);
    }

    /**
     * GET /api/orders/:id
     */
    public function show($id)
    {
        $order = Order::with(['listing', 'passenger', 'driver'])->find($id);
        if (!$order) {
            throw new NotFoundException('Order not found');
        }

        $userId = (int) $this->request->user->id;
        $isParty = (int) $order->passenger_id === $userId || (int) $order->driver_id === $userId;
        $isAdmin = $this->request->user->isAdmin();

        if (!$isParty && !$isAdmin) {
            throw new ForbiddenException('You do not have access to this order');
        }

        $data = $order->toArray();

        // Return action verbs for frontend gating.
        $data['allowed_transitions'] = $order->getAllowedActions();

        // Include cancel_free_until for accepted orders
        if ($order->status === 'accepted' && $order->accepted_at) {
            $data['cancel_free_until'] = date('Y-m-d\TH:i:s\Z', strtotime($order->accepted_at) + 300);
        }

        // Listing summary
        if ($order->listing) {
            $data['listing_summary'] = [
                'id'              => $order->listing->id,
                'title'           => $order->listing->title,
                'pickup_address'  => $order->listing->pickup_address,
                'dropoff_address' => $order->listing->dropoff_address,
            ];
        }

        // Passenger/driver info
        if ($order->passenger) {
            $data['passenger_info'] = [
                'id'   => $order->passenger->id,
                'name' => $order->passenger->name,
            ];
        }
        if ($order->driver) {
            $data['driver_info'] = [
                'id'   => $order->driver->id,
                'name' => $order->driver->name,
            ];
        }

        return json([
            'code'    => 0,
            'message' => 'OK',
            'data'    => $data,
        ], 200);
    }

    /**
     * POST /api/orders/:id/accept
     */
    public function accept($id)
    {
        $order = $this->orderService->accept(
            (int) $id,
            (int) $this->request->user->id
        );

        return json([
            'code'    => 0,
            'message' => 'Order accepted',
            'data'    => $order->toArray(),
        ], 200);
    }

    /**
     * POST /api/orders/:id/start
     */
    public function start($id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundException('Order not found');
        }
        $this->verifyParty($order);

        $order = $this->orderService->start(
            (int) $id,
            (int) $this->request->user->id
        );

        return json([
            'code'    => 0,
            'message' => 'Order started',
            'data'    => $order->toArray(),
        ], 200);
    }

    /**
     * POST /api/orders/:id/complete
     */
    public function complete($id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundException('Order not found');
        }
        $this->verifyParty($order);

        $order = $this->orderService->complete(
            (int) $id,
            (int) $this->request->user->id
        );

        return json([
            'code'    => 0,
            'message' => 'Order completed',
            'data'    => $order->toArray(),
        ], 200);
    }

    /**
     * POST /api/orders/:id/cancel
     */
    public function cancel($id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundException('Order not found');
        }
        $this->verifyParty($order);

        $reasonCode = $this->request->post('reason_code');
        $reasonText = $this->request->post('reason_text');

        // Validate cancel fields if provided
        if ($reasonCode !== null || $reasonText !== null) {
            validate($this->request->post(), 'app\validate\OrderValidate.cancel');
        }

        $order = $this->orderService->cancel(
            (int) $id,
            (int) $this->request->user->id,
            $reasonCode,
            $reasonText
        );

        return json([
            'code'    => 0,
            'message' => 'Order cancelled',
            'data'    => $order->toArray(),
        ], 200);
    }

    /**
     * POST /api/orders/:id/dispute
     */
    public function dispute($id)
    {
        $order = Order::find($id);
        if (!$order) {
            throw new NotFoundException('Order not found');
        }
        $this->verifyParty($order);

        validate($this->request->post(), 'app\validate\OrderValidate.dispute');

        $order = $this->orderService->dispute(
            (int) $id,
            (int) $this->request->user->id,
            $this->request->post('reason')
        );

        return json([
            'code'    => 0,
            'message' => 'Dispute filed',
            'data'    => $order->toArray(),
        ], 200);
    }

    /**
     * POST /api/orders/:id/resolve
     */
    public function resolve($id)
    {
        validate($this->request->post(), 'app\validate\OrderValidate.resolve');

        $order = $this->orderService->resolve(
            (int) $id,
            $this->request->post('resolution'),
            $this->request->post('outcome')
        );

        return json([
            'code'    => 0,
            'message' => 'Order resolved',
            'data'    => $order->toArray(),
        ], 200);
    }

    /**
     * Verify the current user is a party to the order.
     */
    private function verifyParty(Order $order): void
    {
        $userId = (int) $this->request->user->id;
        if ((int) $order->passenger_id !== $userId && (int) $order->driver_id !== $userId) {
            throw new ForbiddenException('You are not a party to this order');
        }
    }
}
