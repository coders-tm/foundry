<?php

namespace Workbench\App\Http\Controllers;

use Foundry\Concerns\Helpers;
use Foundry\Events\SubscriptionCancel;
use Foundry\Events\SubscriptionResume;
use Foundry\Foundry;
use Foundry\Models\Subscription;
use Foundry\Notifications\SubscriptionCancelNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    use Helpers;

    /**
     * Display shop/product subscriptions for the authenticated user.
     */
    public function index(Request $request)
    {
        $query = Subscription::where('user_id', user('id'))
            ->with('plan', 'plan.variant', 'plan.product');

        // Apply status filter if present
        if ($request->query('status')) {
            if ($request->status === 'inactive') {
                $query->whereNotIn('status', ['active', 'trialing', 'pending']);
            } else {
                $statuses = array_map('trim', explode(',', $request->get('status')));
                $query->whereIn('status', $statuses);
            }
        }

        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Transform subscriptions to include additional information
        $subscriptions->getCollection()->each(function ($subscription) {
            $upcomingInvoice = $subscription->upcomingInvoice();

            // Add status messages
            if ($subscription->canceled() && $subscription->canceledOnGracePeriod()) {
                $subscription['message'] = __('You have cancelled your subscription. Your subscription will end on :date', [
                    'date' => $subscription->expires_at->format('d M, Y'),
                ]);
            } elseif ($subscription->expired() || $subscription->hasIncompletePayment()) {
                $invoice = $subscription->latestInvoice;
                $amount = $invoice?->total();
                $subscription['message'] = __('To activate your subscription, please complete payment of :amount.', [
                    'amount' => $amount,
                ]);
                $subscription['invoice'] = [
                    'amount' => $amount,
                    'key' => $invoice?->key,
                ];
                $subscription['hasDue'] = true;
            } elseif ($upcomingInvoice) {
                $subscription['upcomingInvoice'] = [
                    'amount' => $upcomingInvoice->total(),
                    'date' => $upcomingInvoice->due_date->format('d M, Y'),
                ];

                if ($subscription->onTrial()) {
                    $trial_end = $subscription->trial_ends_at->format('M d, Y');
                    $subscription['message'] = "You're on trial until $trial_end. Once it ends, we'll charge you for your subscription.";
                } else {
                    $subscription['message'] = __('Next invoice :amount on :date', [
                        'amount' => $subscription['upcomingInvoice']['amount'],
                        'date' => $subscription['upcomingInvoice']['date'],
                    ]);
                }
            }

            // Add computed properties
            $subscription['canceled'] = $subscription->canceled();
            $subscription['ended'] = $subscription->ended();
            $subscription['is_valid'] = $subscription->valid() ?? false;

            // Clean up relations to avoid circular references
            $subscription->unsetRelation('owner');
        });

        return response()->json($subscriptions, 200);
    }

    /**
     * Show a specific subscription.
     */
    public function show(Request $request, Subscription $subscription)
    {
        // Ensure the subscription belongs to the user and is product-based
        if ($subscription->user_id !== user('id')) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription not found or access denied.',
            ]);
        }

        $subscription->load(['plan.product', 'plan.variant']);
        $upcomingInvoice = $subscription->upcomingInvoice();

        // Add status information
        if ($subscription->canceled() && $subscription->canceledOnGracePeriod()) {
            $subscription['message'] = __('You have cancelled your subscription. Your subscription will end on :date', [
                'date' => $subscription->expires_at->format('d M, Y'),
            ]);
        } elseif ($subscription->expired() || $subscription->hasIncompletePayment()) {
            $invoice = $subscription->latestInvoice;
            $amount = $invoice?->total();
            $subscription['message'] = __('To activate your subscription, please complete payment of :amount.', [
                'amount' => $amount,
            ]);
            $subscription['invoice'] = [
                'amount' => $amount,
                'key' => $invoice?->key,
            ];
            $subscription['hasDue'] = true;
        } elseif ($upcomingInvoice) {
            $subscription['upcomingInvoice'] = [
                'amount' => $upcomingInvoice->total(),
                'date' => $upcomingInvoice->due_date->format('d M, Y'),
            ];
        }

        $subscription['canceled'] = $subscription->canceled();
        $subscription['ended'] = $subscription->ended();
        $subscription['is_valid'] = $subscription->valid() ?? false;

        $subscription->unsetRelation('owner');
        $subscription->unsetRelation('user');

        return response()->json($subscription, 200);
    }

    /**
     * Cancel a product subscription.
     */
    public function cancel(Request $request, Subscription $subscription)
    {
        $user = $this->user();

        // Ensure the subscription belongs to the user and is product-based
        if ($subscription->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription not found or access denied.',
            ]);
        }

        try {
            // Cancel any open invoices before cancelling the subscription
            $subscription->cancelOpenInvoices();

            $subscription->cancel();

            event(new SubscriptionCancel($subscription));

            $user->notify(new SubscriptionCancelNotification($subscription));

            return response()->json([
                'message' => __('You have successfully cancelled your subscription.'),
                'data' => $subscription->fresh(),
            ], 200);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'subscription' => 'Failed to cancel subscription: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Resume a canceled product subscription.
     */
    public function resume(Request $request, Subscription $subscription)
    {
        $user = $this->user();

        // Ensure the subscription belongs to the user and is product-based
        if ($subscription->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription not found or access denied.',
            ]);
        }

        try {
            $subscription = $subscription->resume();

            event(new SubscriptionResume($subscription));

            return response()->json([
                'message' => __('You have successfully resume your subscription.'),
                'data' => $subscription->fresh(),
            ], 200);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'subscription' => 'Failed to resume subscription: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Pay for a past due subscription.
     */
    public function pay(Request $request, Subscription $subscription)
    {
        $user = $this->user();

        // Ensure the subscription belongs to the user and is product-based
        if ($subscription->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription not found or access denied.',
            ]);
        }

        $request->validate([
            'payment_method' => 'required|exists:payment_methods,id',
        ]);

        try {
            $subscription->pay($request->payment_method);

            return response()->json([
                'data' => $subscription->fresh(),
                'message' => __('Due payment has been received.'),
            ], 200);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'payment' => 'Failed to process payment: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get all active product subscriptions for admin.
     */
    public function adminIndex(Request $request)
    {
        // This method is for admin use only
        if (! is_admin()) {
            throw ValidationException::withMessages([
                'access' => 'Access denied.',
            ]);
        }

        $query = Subscription::with(['user', 'plan.product', 'plan.variant']);

        // Apply filters
        if ($request->has('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('status', 'active');
                    break;
                case 'canceled':
                    $query->where('status', 'canceled');
                    break;
                case 'expired':
                    $query->where('status', 'expired');
                    break;
                case 'incomplete':
                    $query->where('status', 'incomplete');
                    break;
            }
        }

        if ($request->has('product_id')) {
            $query->whereHas('plan', function ($q) use ($request) {
                $q->where('product_id', $request->product_id);
            });
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($subscriptions, 200);
    }

    /**
     * Get the authenticated user.
     */
    protected function user()
    {
        if (request()->filled('user_id') && is_admin()) {
            return Foundry::$userModel::findOrFail(request()->user_id);
        }

        return user();
    }
}
