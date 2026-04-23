<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Foundry;
use Foundry\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages subscription orders.
 *
 * Scoped exclusively to orders associated with subscriptions — not
 * a general-purpose shop order controller. Authorization is enforced
 * via the Order policy: users see only their own orders, admins see all.
 */
class OrderController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Order::class, 'order');
    }

    /**
     * List subscription orders for the authenticated user (or all for admin).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Order $model */
        $model = Foundry::$orderModel;

        $query = $model::query()
            ->with(['line_items', 'payments', 'contact'])
            ->latest();

        // Regular users see only their own orders
        if (! $request->user('admin')) {
            $query->onlyOwner();
        }

        if ($request->filled('status')) {
            $query->whereStatus($request->status);
        }

        if ($request->filled('payment_status')) {
            $query->byPaymentStatus($request->payment_status);
        }

        $orders = $query->paginate($request->integer('per_page', 15));

        return response()->json($orders);
    }

    /**
     * Show a single subscription order.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['line_items', 'tax_lines', 'payments', 'contact', 'orderable']);

        return response()->json($order);
    }

    /**
     * Cancel a subscription order (user-initiated).
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        abort_if($order->is_cancelled, 422, __('This order has already been cancelled.'));
        abort_if($order->is_paid, 422, __('Paid orders cannot be self-cancelled. Please contact support.'));

        $order->cancel($request->input('reason'));

        return response()->json([
            'message' => __('Order has been cancelled successfully.'),
            'order' => $order->fresh(['payments']),
        ]);
    }

    /**
     * Update order status (admin only).
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $this->authorize('manage', $order);

        $request->validate([
            'status' => ['required', 'string'],
        ]);

        $order->update(['status' => $request->status]);

        return response()->json([
            'message' => __('Order status updated successfully.'),
            'order' => $order->fresh(),
        ]);
    }
}
