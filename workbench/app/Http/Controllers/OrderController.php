<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\Order\Customer as NotifiableCustomer;
use Foundry\Models\User;
use Foundry\Notifications\OrderInvoiceNotification;
use Foundry\Repository\OrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Foundry::$orderModel);

        $query = Foundry::$orderModel::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('trashed')) {
            $query->onlyTrashed();
        }

        if ($request->has('sort')) {
            $query->orderBy($request->input('sort'), $request->input('order', 'asc'));
        } else {
            $query->latest('created_at');
        }

        $orders = $query->paginate($request->input('per_page', 20))
            ->withQueryString();

        return response()->json([
            'orders' => JsonResource::collection($orders),
            'filters' => (object) $request->only(['search', 'trashed', 'sort', 'order']),
            'trashable' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Foundry::$orderModel);

        $order = Foundry::$orderModel::create($request->all());

        if ($request->filled('payment_method')) {
            $order->markAsPaid($request->payment_method, [
                'note' => 'Marked the manual payment as received',
            ]);
        }

        if ($request->filled('invoice_data')) {
            $this->sendInvoiceNotification($order, $request->input('invoice_data'));
        }

        $order->refresh();

        return redirect()->route('admin.orders.show', $order)
            ->with('success', __('Order has been created successfully!'));
    }

    protected function sendInvoiceNotification(Order $order, array $data)
    {
        $recipientEmail = $data['to'] ?? $order->customer?->email;

        if (empty($recipientEmail)) {
            return;
        }

        $customer = new NotifiableCustomer([
            'id' => $order->customer?->id,
            'first_name' => $order->customer?->first_name,
            'last_name' => $order->customer?->last_name,
            'email' => $recipientEmail,
        ]);

        $order->logs()->create([
            'type' => 'invoice_sent',
            'message' => 'Invoice has been sent to '.$recipientEmail,
        ]);

        $customer->notify(new OrderInvoiceNotification($order));
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);
        $this->authorize('view', $order);

        $order->load([
            'line_items',
            'tax_lines',
            'payments',
            'contact',
            'orderable',
            'discount',
        ]);

        return response()->json($order);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);

        if ($order->trashed()) {
            return redirect()->back()->with('error', __('Deleted orders cannot be edited.'));
        }

        $this->authorize('update', $order);

        if (! ($order->can_edit ?? true) && ! $request->filled('status')) {
            return redirect()->back()->with('error', __('Canceled/Completed orders can’t be edited.'));
        }

        $order->update($request->all());

        if ($request->filled('payment_method')) {
            $order->markAsPaid($request->payment_method, [
                'note' => 'Marked the manual payment as received',
            ]);
        }

        return redirect()->back()
            ->with('success', __('Order has been updated successfully!'));
    }

    /**
     * Remove the specified resource from storage (soft or force delete).
     */
    public function destroy(Request $request, $id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);

        if ($request->boolean('force')) {
            $this->authorize('forceDelete', $order);
            $order->forceDelete();

            return redirect()->back()
                ->with('success', __('Permanently deleted.'));
        }

        $this->authorize('delete', $order);
        $order->delete();

        return redirect()->back()
            ->with('success', __('Deleted successfully.'));
    }

    /**
     * Bulk delete resources (soft or force delete based on ?force param).
     */
    public function bulkDestroy(Request $request)
    {
        $this->authorize('delete', Foundry::$orderModel);

        $ids = $request->input('items', []);
        $force = $request->boolean('force');

        if ($force) {
            Foundry::$orderModel::withTrashed()->whereIn('id', $ids)->forceDelete();

            return redirect()->back()
                ->with('success', __('Selected orders permanently deleted.'));
        }

        Foundry::$orderModel::whereIn('id', $ids)->delete();

        return redirect()->back()
            ->with('success', __('Selected orders deleted successfully.'));
    }

    /**
     * Restore the specified soft-deleted resource.
     */
    public function restore($id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);
        $this->authorize('restore', $order);

        $order->restore();

        return redirect()->back()
            ->with('success', __('Restored successfully.'));
    }

    /**
     * Bulk restore soft-deleted resources.
     */
    public function bulkRestore(Request $request)
    {
        $this->authorize('restore', Foundry::$orderModel);

        $ids = $request->input('items', []);
        Foundry::$orderModel::withTrashed()->whereIn('id', $ids)->restore();

        return redirect()->back()
            ->with('success', __('Selected orders restored successfully.'));
    }

    /**
     * Cancel the specified order.
     */
    public function cancel($id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);
        $this->authorize('update', $order);

        $order->markAsCancelled();

        return redirect()->back()
            ->with('success', __('Order cancelled successfully.'));
    }

    /**
     * Refund the specified order.
     */
    public function refund(Request $request, $id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);
        $this->authorize('update', $order);

        try {
            $order->refund(
                reason: $request->input('reason'),
                toWallet: $request->boolean('to_wallet')
            );

            return redirect()->back()
                ->with('success', __('Refund processed successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Download invoice for the specified order.
     */
    public function downloadInvoice($id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);
        $this->authorize('view', $order);

        return $order->download();
    }

    /**
     * Mark the specified order as paid.
     */
    public function markAsPaid($id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);
        $this->authorize('update', $order);

        $order->markAsPaid();

        return redirect()->back()
            ->with('success', __('Order marked as paid successfully.'));
    }

    /**
     * Mark the specified order as completed.
     */
    public function markAsCompleted($id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);
        $this->authorize('update', $order);

        $order->update(['status' => 'completed']);

        return redirect()->back()
            ->with('success', __('Order marked as completed successfully.'));
    }

    /**
     * Send invoice for the specified order.
     */
    public function sendInvoice(Request $request, $id)
    {
        $order = Foundry::$orderModel::withTrashed()->findOrFail($id);
        $this->authorize('update', $order);

        // Validate email when explicitly provided
        $request->validate([
            'to' => 'nullable|email',
        ]);

        $this->sendInvoiceNotification($order, $request->only(['to', 'subject', 'message']));

        return redirect()->back()
            ->with('success', __('Invoice sent successfully!'));
    }

    /**
     * Calculate order totals based on line items
     *
     * @return JsonResponse
     */
    public function calculator(Request $request)
    {
        $request->merge([
            'line_items' => $request->line_items ?? [],
        ]);

        $order = Foundry::$orderModel::firstOrNew(['id' => $request->id], []);

        if ($request->input('customer.id') !== $order->customer_id) {
            $order->customer_id = $request->input('customer.id');
            $customer = User::find($request->input('customer.id'));
            $request->merge([
                'billing_address' => $customer?->address,
                'customer' => $customer?->toArray(),
            ]);
        }

        // Use OrderRepository to process request and calculate order totals
        $order = OrderRepository::fromRequest($request, $order);

        if ($order->customer_id) {
            $order->loadMissing('customer.address');
        }

        return response()->json($order, 200);
    }
}
