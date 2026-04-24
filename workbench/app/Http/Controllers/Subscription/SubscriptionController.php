<?php

namespace Workbench\App\Http\Controllers\Subscription;

use Carbon\Carbon;
use Foundry\Events\SubscriptionCancel;
use Foundry\Events\SubscriptionPlanChanged;
use Foundry\Events\SubscriptionResume;
use Foundry\Events\SubscriptionUpgraded;
use Foundry\Foundry;
use Foundry\Models\Coupon;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Redeem;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Notifications\SubscriptionCancelNotification;
use Foundry\Notifications\SubscriptionDowngradeNotification;
use Foundry\Notifications\SubscriptionUpgradeNotification;
use Foundry\Services\Admin\SubscriptionService;
use Foundry\Services\GatewaySubscriptionFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use Workbench\App\Http\Controllers\Controller;

class SubscriptionController extends Controller
{
    /**
     * Display subscriptions for authenticated user or admin.
     *
     * For users: Shows only their own subscriptions
     * For admins: Can filter by user or view all subscriptions
     */
    public function index(Request $request)
    {
        // Determine relations to load based on access level
        $relations = ['plan', 'plan.variant', 'plan.product', 'latestInvoice'];

        if (is_admin()) {
            $relations[] = 'user';
        }

        // Get all product-based subscriptions
        $query = Foundry::$subscriptionModel::with($relations);

        // Apply user filtering based on access level
        if (is_user()) {
            // For users, restrict to their own subscriptions
            $query->where('user_id', user('id'))
                ->orderBy('type');
        } elseif ($request->filled('user')) {
            $query->where('user_id', $request->user);
        }

        // Apply status filter if present
        if ($request->query('status')) {
            if ($request->status === 'inactive') {
                $query->whereNotIn('status', ['active', 'trialing']);
            } else {
                $statuses = array_map('trim', explode(',', $request->get('status')));
                $query->whereIn('status', $statuses);
            }
        }

        $subscriptions = $query->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?: 15);

        // Transform subscriptions to include additional information
        $subscriptions->getCollection()->transform(function ($subscription) {
            return (new Subscription)->forceFill($this->transformSubscription($subscription));
        });

        return new ResourceCollection($subscriptions);
    }

    /**
     * Get current user's subscription details.
     */
    public function current(Request $request)
    {
        /** @var User */
        $user = $this->user($request);
        /** @var Subscription $subscription */
        $subscription = $user->subscription();

        if ($user->onGenericTrial()) {
            return response()->json([
                'on_generic_trial' => true,
                'trial_ends_at' => $user->trial_ends_at,
            ], 200);
        } elseif ($user->is_free_forever || ! $subscription) {
            return response()->json([
                'is_free_forever' => (bool) $user->is_free_forever,
            ], 200);
        }

        $data = $subscription->toResponse(['plan', 'usages', 'next_plan']);

        return response()->json($data, 200);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan' => 'required|exists:plans,id',
            'payment_method' => 'required|exists:payment_methods,id',
        ], [
            'payment_method.required_unless' => __('Please select a payment method to proceed with the subscription.'),
        ]);

        /** @var User */
        $user = $this->user($request);

        $upgrade = $downgrade = false;
        $paymentMethod = PaymentMethod::find($request->payment_method);
        $provider = $paymentMethod?->provider;

        if ($request->filled('subscription')) {
            $subscription = Subscription::find($request->subscription);
        } else {
            /** @var Subscription $subscription */
            $subscription = $user->subscription();
        }

        $plan = Plan::find($request->plan);
        $subscribed = $user->subscribed();
        $trial_end = $user->trial_ends_at;
        $trial_days = $plan->trial_days;
        $coupon = optional(Coupon::findByCode($request->promotion_code));
        $oldPlan = null;

        // Check for existing subscription with same plan
        if ($subscribed && $subscription->plan_id == $plan->id) {
            throw ValidationException::withMessages([
                'plan' => __('You already subscribed to :plan plan.', ['plan' => $plan->label]),
            ]);
        }

        try {
            if ($subscription) {
                $downgrade = $plan->price < $subscription->plan->price;

                if ($downgrade) {
                    $this->downgrade($subscription, [
                        'plan' => $plan->id,
                    ]);
                } else {
                    $oldPlan = $subscription->plan;
                    $subscription = $subscription->withCoupon($request->promotion_code)
                        ->swap($plan->id);

                    $upgrade = true;
                }
            } else {
                $subscription = $user->newSubscription('default', $plan->id)
                    ->withCoupon($request->promotion_code)
                    ->setProvider($provider);

                if ($trial_end && $trial_end->isFuture()) {
                    $subscription->trialUntil($trial_end);
                } elseif ($trial_days && ! $trial_end) {
                    $subscription->trialDays($trial_days);
                }

                $subscription->saveAndInvoice();
            }
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            if ($coupon->id && $subscription) {
                Redeem::updateOrCreate([
                    'redeemable_type' => get_class($subscription),
                    'redeemable_id' => $subscription->id,
                    'coupon_id' => $coupon->id,
                ], [
                    'user_id' => $user->id,
                    'amount' => $coupon->getAmount($plan->price),
                ]);
            }

            if ($upgrade) {
                $subscription->syncOrResetUsages();
                $subscription->oldPlan = $oldPlan;
                $subscription->plan = $plan;

                event(new SubscriptionUpgraded($subscription));

                $user->notify(new SubscriptionUpgradeNotification($subscription));
            }

            $gateway = GatewaySubscriptionFactory::make($subscription);

            return response()->json($gateway->setup(), 200);
        }
    }

    /**
     * Pay for a subscription (Admin only).
     */
    public function pay(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'required|exists:payment_methods,id',
        ]);

        /** @var Subscription $subscription */
        $subscription = Foundry::$subscriptionModel::findOrFail($id);

        // Determine payment type for message before processing
        $isOutstanding = $subscription->hasIncompletePayment() || $subscription->onGracePeriod();

        // Use core pay method which automatically handles payment type detection
        $subscription->pay($request->payment_method);

        // Determine appropriate message based on payment type
        if ($isOutstanding) {
            $message = __('Outstanding payment processed. Subscription reactivated.');
        } else {
            $message = __('Renewal payment processed successfully.');
        }

        return response()->json([
            'data' => $this->transformSubscription($subscription->fresh()),
            'message' => $message,
        ], 200);
    }

    /**
     * Create a new subscription for a user (Admin only).
     *
     * Validates all input, then delegates to service for business logic.
     * Returns the created/modified subscription with appropriate HTTP status.
     *
     * Request body:
     * - plan (required): Plan ID to subscribe to
     * - user (required): User to subscribe
     * - starts_at (optional): Custom start date
     * - expires_at (optional): Custom expiry date
     * - trial_days (optional): Override plan trial days
     * - promotion_code (optional): Coupon code to apply
     * - force (optional): Force plan swap even if same plan
     * - mark_as_paid (optional): Immediately mark subscription as paid
     * - payment_method (required if mark_as_paid): Payment method for payment
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        // Validate all input upfront
        $validated = $request->validate([
            'plan' => 'required',
            'user' => 'required_without:user_id',
            'user_id' => 'required_without:user',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'trial_days' => 'nullable|integer|min:0',
            'promotion_code' => 'nullable|string',
            'force' => 'sometimes|boolean',
            'generate_invoice' => 'sometimes|boolean',
            'mark_as_paid' => 'sometimes|boolean',
            'payment_method' => 'nullable',
        ]);

        $validated['user'] = $validated['user'] ?? $validated['user_id'];


        // Resolve and validate user exists
        try {
            $user = Foundry::$userModel::findOrFail($validated['user']);
        } catch (\Exception) {
            throw ValidationException::withMessages([
                'user' => __('The specified user does not exist.'),
            ]);
        }

        $service = app(SubscriptionService::class);

        // Delegate to service for all business logic
        $subscription = $service->createOrUpdate($user, $validated);

        return response()->json([
            'message' => __('Subscription has been created successfully.'),
            'data' => $this->transformSubscription($subscription),
        ], 201);
    }

    /**
     * Update a subscription (Admin only).
     *
     * Validates all input, then delegates to service for business logic.
     * Supports plan swaps, custom dates, trial configuration, and payment marking.
     *
     * @param  int  $id  Subscription ID
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Validate all input upfront
        $validated = $request->validate([
            'plan' => 'required|integer',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'trial_days' => 'nullable|integer|min:0',
            'promotion_code' => 'nullable|string',
            'force' => 'sometimes|boolean',
            'mark_as_paid' => 'sometimes|boolean',
            'payment_method' => 'nullable|integer',
        ]);

        // Resolve and validate subscription exists
        try {
            $subscription = Foundry::$subscriptionModel::findOrFail($id);
        } catch (\Exception) {
            throw ValidationException::withMessages([
                'id' => __('The specified subscription does not exist.'),
            ]);
        }

        $service = app(SubscriptionService::class);

        // Delegate to service for all business logic
        $subscription = $service->createOrUpdate($subscription->user, $validated, $subscription);

        return response()->json([
            'message' => __('Subscription has been updated successfully.'),
            'data' => $this->transformSubscription($subscription),
        ], 200);
    }

    /**
     * Display a specific subscription.
     *
     * For users: Can only view their own subscriptions (use 'current' or subscription ID)
     * For admins: Can view any subscription
     */
    public function show(Request $request, $id)
    {
        /** @var Subscription $subscription */
        $subscription = $this->resolveSubscription($id);

        $this->assertUserSubscriptionAccess($subscription);

        $subscription->load(['plan.product', 'plan.variant']);

        $data = $this->transformSubscription($subscription);

        return response()->json($data, 200);
    }

    /**
     * Cancel a product subscription.
     *
     * For users: Can only cancel their own subscriptions (respects contracts)
     * For admins: Can cancel any subscription
     */
    public function cancel(Request $request, $id)
    {
        /** @var Subscription $subscription */
        $subscription = $this->resolveSubscription($id);

        // Check access for users
        if (is_user()) {
            $this->assertUserSubscriptionAccess($subscription);

            // Users cannot cancel contracts
            if ($subscription->isContract()) {
                throw ValidationException::withMessages([
                    'subscription' => __('Contract subscriptions cannot be cancelled until the end of their term. Please contact support for assistance.'),
                ]);
            }
        }

        if ($request->input('immediately', false)) {
            $subscription->cancelNow();
        } else {
            $subscription->cancel();
        }

        // cancel all open membership invoices first
        $subscription->cancelOpenInvoices();

        $user = $subscription->user;

        event(new SubscriptionCancel($subscription));

        if ($user) {
            $user->notify(new SubscriptionCancelNotification($subscription));
        }

        return response()->json([
            'message' => __('Subscription cancelled successfully.'),
            'data' => $this->transformSubscription($subscription->fresh()),
        ], 200);
    }

    /**
     * Resume a canceled product subscription.
     *
     * For users: Can only resume their own subscriptions
     * For admins: Can resume any subscription
     */
    public function resume(Request $request, $id)
    {
        /** @var Subscription $subscription */
        $subscription = $this->resolveSubscription($id);

        $this->assertUserSubscriptionAccess($subscription);

        try {
            $subscription = $subscription->resume();

            event(new SubscriptionResume($subscription));

            return response()->json([
                'message' => __('Subscription resumed successfully.'),
                'data' => $this->transformSubscription($subscription->fresh()),
            ], 200);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'subscription' => 'Failed to resume subscription: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel a scheduled subscription downgrade.
     *
     * For users: Can only cancel their own scheduled downgrades
     * For admins: Can cancel any scheduled downgrade
     */
    public function cancelDowngrade(Request $request, $id)
    {
        /** @var Subscription $subscription */
        $subscription = $this->resolveSubscription($id);

        $this->assertUserSubscriptionAccess($subscription);

        $subscription->cancelDowngrade();

        return response()->json([
            'message' => __('Subscription downgrade cancelled successfully.'),
            'data' => $this->transformSubscription($subscription->fresh()),
        ], 200);
    }

    /**
     * Renew a subscription - only for subscriptions with incomplete payments.
     */
    public function renew(Request $request, $id)
    {
        /** @var Subscription $subscription */
        $subscription = Foundry::$subscriptionModel::findOrFail($id);

        // Check access: admins can renew any subscription, users only their own
        if ($subscription->user_id !== user('id')) {
            throw ValidationException::withMessages([
                'subscription' => __('Subscription not found or access denied.'),
            ]);
        }

        // Only allow renewal if subscription has incomplete payment
        if (! $subscription->hasIncompletePayment()) {
            throw ValidationException::withMessages([
                'subscription' => __('This subscription does not have any incomplete payments that require renewal.'),
            ]);
        }

        // Get the latest invoice for incomplete payment
        $latestInvoice = $subscription->latestInvoice;

        if ($latestInvoice && ! $latestInvoice->is_paid) {
            // There's an outstanding invoice - redirect to payment
            return response()->json([
                'redirect' => true,
                'token' => $latestInvoice->id,
                'amount' => $latestInvoice->total(),
                'redirect_url' => user_route("payment/{$latestInvoice->id}"),
                'message' => __('Please complete the pending payment to renew your subscription'),
            ], 200);
        } else {
            throw ValidationException::withMessages([
                'subscription' => 'No outstanding invoice found for this subscription.',
            ]);
        }
    }

    /**
     * Freeze a subscription.
     *
     * For users: Can only freeze their own subscriptions
     * For admins: Can freeze any subscription
     */
    public function freeze(Request $request, $id)
    {
        $request->validate([
            'release_at' => 'required|date|after:today',
            'reason' => 'nullable|string|max:500',
        ]);

        /** @var Subscription */
        $subscription = Subscription::findOrFail($id);

        // Check user access (uses parent's method)
        $this->assertUserSubscriptionAccess($subscription);

        // Check if subscription can be frozen
        if (! $subscription->canFreeze()) {
            return response()->json([
                'message' => __('This subscription cannot be frozen.'),
            ], 422);
        }

        // Get freeze fee from plan
        $freezeFee = $subscription->plan->freeze_fee ?? 0;

        // Freeze the subscription (convert string to Carbon instance)
        $subscription->freeze(
            Carbon::parse($request->release_at),
            $request->reason,
            $freezeFee
        );

        return response()->json([
            'data' => $this->transformSubscription($subscription->fresh()),
            'message' => __('Subscription has been frozen successfully!'),
        ], 200);
    }

    /**
     * Unfreeze a subscription.
     *
     * For users: Can only unfreeze their own subscriptions
     * For admins: Can unfreeze any subscription
     */
    public function unfreeze(Request $request, $id)
    {
        /** @var Subscription */
        $subscription = Subscription::findOrFail($id);

        // Check user access (uses parent's method)
        $this->assertUserSubscriptionAccess($subscription);

        // Check if subscription is frozen
        if (! $subscription->onFreeze()) {
            return response()->json([
                'message' => __('This subscription is not frozen.'),
            ], 422);
        }

        // Unfreeze the subscription
        $subscription->unfreeze();

        return response()->json([
            'data' => $this->transformSubscription($subscription->fresh()),
            'message' => __('Subscription has been unfrozen successfully!'),
        ], 200);
    }

    /**
     * Get invoices for a subscription.
     *
     * For users: Can only view invoices for their own subscriptions
     * For admins: Can view invoices for any subscription
     */
    public function invoices(Request $request, $id)
    {
        /** @var Subscription $subscription */
        $subscription = $this->resolveSubscription($id);

        $this->assertUserSubscriptionAccess($subscription);

        $query = $subscription->invoices();

        // Apply status filter if provided
        if ($request->filled('status')) {
            $query->whereStatus($request->status);
        }

        $invoices = $query->orderBy($request->sortBy ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->rowsPerPage ?: 15);

        return new ResourceCollection($invoices);
    }

    /**
     * Check validity of a promo code for a given plan.
     */
    public function checkPromoCode(Request $request)
    {
        $request->validate([
            'promotion_code' => 'required|string',
            'plan_id' => 'required',
        ]);

        $planId = $request->input('plan_id');
        $couponCode = $request->input('promotion_code');

        // Retrieve the coupon from the database (without active filter)
        $coupon = Coupon::where('promotion_code', $couponCode)->first();

        // Validate if the coupon exists
        if (! $coupon) {
            throw ValidationException::withMessages([
                'promotion_code' => ['Invalid coupon code'],
            ]);
        }

        // if the coupon is not active
        if (! $coupon->isActive()) {
            throw ValidationException::withMessages([
                'promotion_code' => ['Coupon is not active'],
            ]);
        }

        // if the coupon is expired
        if ($coupon->isExpired()) {
            throw ValidationException::withMessages([
                'promotion_code' => ['Coupon has expired'],
            ]);
        }

        if (! $coupon->canApplyToPlan($planId)) {
            throw ValidationException::withMessages([
                'promotion_code' => ['Invalid coupon code'],
            ]);
        }

        // Check if the coupon has reached its maximum redemptions
        if ($coupon->checkMaxRedemptions()) {
            throw ValidationException::withMessages([
                'promotion_code' => [__('Coupon has reached maximum redemptions')],
            ]);
        }

        return response()->json($coupon->toPublic());
    }

    /**
     * Handle subscription downgrade.
     *
     * @param  Subscription  $subscription
     * @return void
     */
    protected function downgrade($subscription, array $options = [])
    {
        if (! isset($options['plan']) || ! $options['plan']) {
            throw ValidationException::withMessages([
                'plan' => __('A valid plan is necessary for downgrading the subscription.'),
            ]);
        }

        try {
            $oldPlan = Plan::find($subscription->plan_id);
            $newPlan = Plan::find($options['plan']);
            /** @var User */
            $user = $subscription->user;

            // Schedule downgrade for next renewal (current behavior)
            $subscription->is_downgrade = true;
            $subscription->next_plan = $options['plan'];
            $subscription->save();

            // Set custom properties for notification
            $subscription->oldPlan = $oldPlan;
            $subscription->plan = $newPlan;

            // Update the subscription plan
            event(new SubscriptionPlanChanged($subscription, $oldPlan, $newPlan));

            if ($user) {
                $user->notify(new SubscriptionDowngradeNotification($subscription));
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Resolve subscription by ID with error handling.
     *
     * @param  mixed  $id  Subscription ID
     * @return Subscription
     *
     * @throws ValidationException
     */
    protected function resolveSubscription($id)
    {
        return Foundry::$subscriptionModel::findOrFail($id);
    }

    /**
     * Assert that the authenticated user has access to the given subscription.
     */
    protected function assertUserSubscriptionAccess($subscription)
    {
        if (is_user() && $subscription->user_id != user('id')) {
            throw ValidationException::withMessages([
                'subscription' => __('You do not have access to this subscription.'),
            ]);
        }
    }

    /**
     * Transform subscription with additional information.
     *
     * For users: Returns basic subscription data
     * For admins: Includes user information in the response
     *
     * @param  Subscription  $subscription
     */
    protected function transformSubscription($subscription, array $extends = ['usages', 'plan', 'next_plan']): array
    {
        if (is_admin()) {
            $extends[] = 'user';
        }

        return $subscription->toResponse($extends);
    }

    /**
     * Get the requesting user.
     *
     * @return User
     */
    protected function user(Request $request)
    {
        if (is_admin()) {
            return Foundry::$userModel::find($request->input('user'));
        }

        return user();
    }
}
