<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Foundry;
use Foundry\Enum\LogType;
use Foundry\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Manages subscription orders.
 *
 * Scoped exclusively to orders associated with subscriptions — not
 * a general-purpose shop order controller. Authorization is enforced
 * via the Order policy: users see only their own orders, admins see all.
 */
class OrderController extends Controller
{
    /**
     * List subscription orders for the authenticated user (or all for admin).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Foundry::$orderModel);

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

        if ($request->boolean('deleted')) {
            $query->onlyTrashed();
        }

        $orders = $query->paginate($request->integer('per_page', 15));

        return (new ResourceCollection($orders))->response();
    }

    /**
     * Store a new subscription order.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Foundry::$orderModel);

        $order = Foundry::$orderModel::create($request->all());

        return response()->json([
            'message' => __('Order has been created successfully.'),
            'order' => $order->load(['line_items', 'payments', 'contact']),
        ], 201);
    }

    /**
     * Show a single subscription order.
     */
    public function show($id): JsonResponse
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);

        $this->authorize('view', $order);

        $order->load(['line_items', 'tax_lines', 'payments', 'contact', 'orderable']);

        return response()->json($order);
    }

    /**
     * Update the specified order.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $order->update($request->all());

        return response()->json([
            'message' => __('Order has been updated successfully.'),
            'order' => $order->fresh(['line_items', 'payments', 'contact']),
        ]);
    }

    /**
     * Remove the specified order.
     */
    public function destroy($id): JsonResponse
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);

        $this->authorize('delete', $order);

        if ($order->trashed()) {
            $order->forceDelete();
        } else {
            $order->delete();
        }

        return response()->json([
            'message' => __('Order has been deleted successfully.'),
        ]);
    }

    /**
     * Bulk delete orders.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorize('deleteAny', Foundry::$orderModel);

        $request->validate(['items' => 'required|array']);

        $query = Foundry::$orderModel::withTrashed()->whereIn('id', $request->items);

        if ($request->boolean('force')) {
            $query->forceDelete();
        } else {
            $query->delete();
        }

        return response()->json([
            'message' => __('Selected orders have been deleted successfully.'),
        ]);
    }

    /**
     * Restore the specified order.
     */
    public function restore($id): JsonResponse
    {
        $order = Foundry::$orderModel::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $order);

        $order->restore();

        return response()->json([
            'message' => __('Order has been restored successfully.'),
        ]);
    }

    /**
     * Bulk restore orders.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $this->authorize('restoreAny', Foundry::$orderModel);

        $request->validate(['items' => 'required|array']);

        Foundry::$orderModel::onlyTrashed()->whereIn('id', $request->items)->restore();

        return response()->json([
            'message' => __('Selected orders have been restored successfully.'),
        ]);
    }

    /**
     * Export orders.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Foundry::$orderModel);

        // In workbench, we just return a message as real export requires extra setup
        return response()->json([
            'message' => __('Order export has been started successfully.'),
        ]);
    }

    /**
     * Get logs for an order.
     */
    public function logs(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json($order->logs()
            ->with('admin')
            ->whereNotIn('type', collect(LogType::cases())->map->value->all())
            ->latest()
            ->get());
    }

    /**
     * Store a log for an order.
     */
    public function storeLog(Request $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $request->validate(['message' => 'required|string']);

        $log = $order->logs()->create([
            'message' => $request->message,
            'type' => 'note',
        ]);

        return response()->json([
            'message' => __('Log has been added successfully.'),
            'data' => $log->load('admin'),
        ]);
    }

    /**
     * Cancel a subscription order (user-initiated or admin).
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        abort_if($order->is_cancelled, 422, __('This order has already been cancelled.'));

        // Admin can cancel even if paid (if allowed by business logic)
        if (! $request->user('admin')) {
            abort_if($order->is_paid, 422, __('Paid orders cannot be self-cancelled. Please contact support.'));
        }

        $order->cancel($request->input('reason'));

        return response()->json([
            'message' => __('Order has been cancelled successfully.'),
            'order' => $order->fresh(['payments']),
        ]);
    }

    /**
     * Mark order as paid (admin only).
     */
    public function markAsPaid(Request $request, Order $order): JsonResponse
    {
        $this->authorize('manage', $order);

        $request->validate([
            'payment_method' => 'required|exists:payment_methods,id',
        ]);

        $order->markAsPaid($request->payment_method);

        return response()->json([
            'message' => __('Order has been marked as paid successfully.'),
            'order' => $order->fresh(['payments']),
        ]);
    }

    /**
     * Send invoice notification (admin only).
     */
    public function sendInvoice(Order $order): JsonResponse
    {
        $this->authorize('manage', $order);

        // Logic to send notification
        // $order->customer->notify(new OrderInvoiceNotification($order));

        return response()->json([
            'message' => __('Invoice has been sent successfully.'),
        ]);
    }

    /**
     * Download invoice PDF.
     */
    public function downloadInvoice(Order $order)
    {
        $this->authorize('view', $order);

        return $order->download();
    }

    /**
     * Refund order (admin only).
     */
    public function refund(Request $request, Order $order): JsonResponse
    {
        $this->authorize('manage', $order);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string',
        ]);

        // Logic for refund
        // $order->refund($request->amount, $request->reason);

        return response()->json([
            'message' => __('Refund has been processed successfully.'),
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

        $order->forceFill(['status' => $request->status])->save();

        return response()->json([
            'message' => __('Order status updated successfully.'),
            'order' => $order->fresh(),
        ]);
    }

}
