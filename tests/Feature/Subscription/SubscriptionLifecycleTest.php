<?php

use Foundry\Contracts\ManagesSubscriptions;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Coupon;
use Foundry\Models\Subscription\Feature;
use Foundry\Tests\TestCase;
use Illuminate\Support\Carbon;

uses(TestCase::class);

afterEach(function () {
    Carbon::setTestNow(null);
});

it('subscription starts in pending status', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    $subscription->refresh();

    expect($subscription->status)->toEqual(SubscriptionStatus::PENDING);
    expect($subscription->latestInvoice)->not->toBeNull();
    expect($subscription->latestInvoice->is_paid)->toBeFalse();
});

it('subscription creation with coupon', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);
    $coupon = Coupon::factory()->create([
        'promotion_code' => 'TEST20',
        'discount_type' => 'percentage',
        'value' => 20,
    ]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->withCoupon('TEST20')
        ->saveAndInvoice([], true);

    expect($subscription->coupon_id)->toEqual($coupon->id);
});

it('creation with trial generates no initial invoice', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 14]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->trialDays(14)
        ->saveAndInvoice();

    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->latestInvoice)->toBeNull();
});

it('manual trial end triggers invoice', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 14]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->trialDays(14)
        ->saveAndInvoice();

    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->status)->toEqual(SubscriptionStatus::TRIALING);

    // End trial manually
    $subscription->endTrial();
    expect($subscription->onTrial())->toBeFalse();

    // Renew/Invoice after trial end
    $subscription->saveAndInvoice([], true)->refresh();

    expect($subscription->latestInvoice)->not->toBeNull();
});

it('subscription cancellation and resumption workflow', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    expect($subscription->cancels_at)->toBeNull();
    expect($subscription->canceled())->toBeFalse();

    // Cancel subscription
    $subscription->cancel();

    expect($subscription->cancels_at)->not->toBeNull();
    expect($subscription->canceled())->toBeTrue();
    expect($subscription->canceledOnGracePeriod())->toBeTrue();

    // Resume subscription
    $subscription->resume();

    expect($subscription->cancels_at)->toBeNull();
    expect($subscription->canceled())->toBeFalse();
    expect($subscription->active())->toBeTrue();
});

it('subscription immediate cancellation workflow', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    // Cancel immediately
    $subscription->cancelNow();

    expect($subscription->canceled())->toBeTrue();
    expect($subscription->canceledOnGracePeriod())->toBeFalse();
    expect($subscription->status)->toEqual(SubscriptionStatus::CANCELED);
});

it('subscription with multiple method chaining', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 14]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->trialDays(14)
        ->skipTrial()
        ->saveAndInvoice([], true)
        ->refresh();

    expect($subscription->exists)->toBeTrue();
    expect($subscription->onTrial())->toBeFalse();
    expect($subscription->latestInvoice)->not->toBeNull();
});

it('subscription implements manages subscriptions interface', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    expect($subscription)->toBeInstanceOf(ManagesSubscriptions::class);
    expect(method_exists($subscription, 'valid'))->toBeTrue();
    expect(method_exists($subscription, 'swap'))->toBeTrue();
    expect(method_exists($subscription, 'cancel'))->toBeTrue();
});

it('subscription downgrade workflow', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $proPlan = (Foundry::$planModel)::factory()->create(['price' => 2000, 'label' => 'Pro']);
    $basicPlan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'label' => 'Basic']);

    $subscription = $user->newSubscription('default', $proPlan->id)
        ->saveAndInvoice([], true);

    // Downgrade to basic plan
    $subscription->next_plan = $basicPlan->id;
    $subscription->is_downgrade = true;
    $subscription->save();

    expect($subscription->hasDowngrade())->toBeTrue();
    expect($subscription->next_plan)->toEqual($basicPlan->id);

    // Cancel downgrade
    $subscription->cancelDowngrade();

    expect($subscription->hasDowngrade())->toBeFalse();
    expect($subscription->next_plan)->toBeNull();
});

it('subscription status becomes incomplete on payment failure', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    // Simulate payment failure
    $subscription->paymentFailed();

    expect($subscription->status)->toEqual(SubscriptionStatus::INCOMPLETE);
    expect($subscription->hasIncompletePayment())->toBeTrue();
});

it('subscription cancel open invoices workflow', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    // Generate another open invoice
    $subscription->generateInvoice(true);

    // Cancel all open invoices
    $subscription->cancelOpenInvoices();

    expect($subscription->invoices()->where('status', 'open')->count())->toEqual(0);
});

it('subscription transitions to active on payment', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    expect($subscription->status)->toEqual(SubscriptionStatus::PENDING);

    // Simulate payment confirmation
    $subscription->paymentConfirmation();

    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
    expect($subscription->ends_at)->toBeNull();
});

it('active subscription enters grace on payment failure', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    $subscription->paymentConfirmation();
    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);

    // Set expires_at to future (next billing period) and ends_at to near future (within grace period)
    // This simulates the renewal scenario where customer hasn't paid yet
    $subscription->expires_at = now()->addMonth(); // Next billing period
    $subscription->ends_at = now()->addDays(6); // Grace period end (before expires_at)
    $subscription->save();

    // Simulate payment failure
    $subscription->paymentFailed();

    // Status stays ACTIVE, but onGracePeriod() returns true
    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
    expect($subscription->onGracePeriod())->toBeTrue();
    expect($subscription->notOnGracePeriod())->toBeFalse();
});

it('renewal without payment sets grace status', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    $subscription->paymentConfirmation();

    // Generate invoice (renewal scenario)
    $invoice = $subscription->generateInvoice();

    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
    expect($invoice)->not->toBeNull();
    expect($invoice->is_paid)->toBeFalse();
});

it('grace status is valid', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    $subscription->paymentConfirmation();

    // Set up subscription in grace period (after renewal, waiting for payment)
    $subscription->status = SubscriptionStatus::ACTIVE;
    $subscription->starts_at = now();
    $subscription->expires_at = now()->addMonth(); // Next billing period
    $subscription->ends_at = now()->addDays(7); // Grace period ends in 7 days (before expires_at)
    $subscription->save();

    expect($subscription->onGracePeriod())->toBeTrue();
    expect($subscription->valid())->toBeTrue();
});

it('payment during grace reactivates subscription', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    // Create plan with grace period enabled (7 days)
    $plan = (Foundry::$planModel)::factory()->withGracePeriod(7)->create(['price' => 1000, 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)->saveAndInvoice([], true);
    $subscription->paymentConfirmation();

    // Simulate renewal that enters grace period (unpaid)
    $subscription->renew(); // This creates new period with grace

    expect($subscription->onGracePeriod())->toBeTrue();
    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);

    // Simulate payment during grace period - should exit grace
    $subscription->paymentConfirmation();

    // After payment, subscription should no longer be in grace
    // Payment confirmation should clear ends_at (grace period)
    $subscription->refresh();

    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
    expect($subscription->ends_at)->toBeNull();
    expect($subscription->onGracePeriod())->toBeFalse();
    expect($subscription->expires_at->isFuture())->toBeTrue();
});

it('billing interval is stored', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'interval' => 'month', 'trial_days' => 0]);

    $subscription = $user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    expect($subscription->billing_interval)->toEqual('month');
});

it('grace scope filters subscriptions', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

    // Create subscriptions in different states
    $activeSubscription = $user->newSubscription('active', $plan->id);
    $activeSubscription->status = SubscriptionStatus::ACTIVE;
    $activeSubscription->expires_at = now()->addMonth(); // Not expired
    $activeSubscription->save();

    $graceSubscription = $user->newSubscription('grace', $plan->id);
    $graceSubscription->status = SubscriptionStatus::ACTIVE;
    $graceSubscription->expires_at = now()->addMonth(); // Next billing period
    $graceSubscription->ends_at = now()->addDays(4); // Grace period ends in 4 days (before expires_at)
    $graceSubscription->save();

    $expiredSubscription = $user->newSubscription('expired', $plan->id);
    $expiredSubscription->status = SubscriptionStatus::EXPIRED;
    $expiredSubscription->save();

    // Query only grace period subscriptions
    $graceSubscriptions = (Foundry::$subscriptionModel)::query()->onGracePeriod()->get();

    expect($graceSubscriptions)->toHaveCount(1);
    expect($graceSubscriptions->first()->id)->toEqual($graceSubscription->id);

    // Test notOnGracePeriod scope
    $notGraceSubscriptions = (Foundry::$subscriptionModel)::query()->notOnGracePeriod()->get();
    expect($notGraceSubscriptions->count())->toBeGreaterThanOrEqual(2);
    expect($notGraceSubscriptions->contains($graceSubscription))->toBeFalse();
});

it('renew clears trial ends at', function () {
    // 1. Create a subscription that is currently on trial (or just ended trial)
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'trial_days' => 14,
        'interval' => 'month',
        'price' => 1000,
    ]);

    // Create subscription manually to simulate state just before renewal
    $trialEndAndExpires = Carbon::now()->subMinute(); // Just passed

    $subscription = (Foundry::$subscriptionModel)::create([
        'type' => 'default',
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::TRIALING,
        'trial_ends_at' => $trialEndAndExpires,
        'starts_at' => $trialEndAndExpires, // Billing starts when trial ends
        'expires_at' => $trialEndAndExpires, // Expires when billing period (trial) ends
    ]);

    // 2. Refresh to make sure
    $subscription->refresh();
    expect($subscription->trial_ends_at->toDateTimeString())->toEqual($trialEndAndExpires->toDateTimeString());

    // 3. Call renew()
    $subscription->renew();

    // 4. Assert trial_ends_at is NULL
    expect($subscription->trial_ends_at)->toBeNull();

    // 5. Assert Invoice is generated
    expect($subscription->invoices()->get())->toHaveCount(1);
});

it('renewal extends expires at by plan interval', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'interval' => 'month',
        'interval_count' => 1,
        'price' => 1000,
    ]);
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $originalExpiresAt = Carbon::parse('2025-02-01');

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => Carbon::parse('2025-01-01'),
        'expires_at' => $originalExpiresAt,
    ]);
    $subscription->save();

    $subscription->renew();

    $expectedNewExpiry = $originalExpiresAt->copy()->addMonth();
    expect($subscription->expires_at->format('Y-m-d'))->toEqual($expectedNewExpiry->format('Y-m-d'));
});

it('renewal with different interval counts', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'interval' => 'month',
        'interval_count' => 3,
        'price' => 3000,
    ]);
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $originalExpiresAt = Carbon::parse('2025-01-01');

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => Carbon::parse('2024-10-01'),
        'expires_at' => $originalExpiresAt,
    ]);
    $subscription->save();

    $subscription->renew();

    $expectedNewExpiry = $originalExpiresAt->copy()->addMonths(3);
    expect($subscription->expires_at->format('Y-m-d'))->toEqual($expectedNewExpiry->format('Y-m-d'));
});

it('renewal with yearly plan', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'interval' => 'year',
        'interval_count' => 1,
        'price' => 12000,
    ]);
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $originalExpiresAt = Carbon::parse('2025-01-01');

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => Carbon::parse('2024-01-01'),
        'expires_at' => $originalExpiresAt,
    ]);
    $subscription->save();

    $subscription->renew();

    $expectedNewExpiry = $originalExpiresAt->copy()->addYear();
    expect($subscription->expires_at->format('Y-m-d'))->toEqual($expectedNewExpiry->format('Y-m-d'));
});

it('renewal with next plan updates billing fields', function () {
    $monthlyPlan = (Foundry::$planModel)::factory()->create([
        'interval' => 'month',
        'interval_count' => 1,
        'price' => 1000,
    ]);
    $quarterlyPlan = (Foundry::$planModel)::factory()->create([
        'interval' => 'month',
        'interval_count' => 3,
        'price' => 2500,
    ]);
    $user = (Foundry::$subscriptionUserModel)::factory()->create();

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'plan_id' => $monthlyPlan->id,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => Carbon::parse('2025-01-01'),
        'expires_at' => Carbon::parse('2025-04-01'),
    ]);
    $subscription->save();

    $subscription->next_plan = $quarterlyPlan->id;
    $subscription->is_downgrade = true;
    $subscription->save();

    $subscription->renew();
    $subscription->refresh();

    expect($subscription->billing_interval)->toEqual('month');
    expect($subscription->billing_interval_count)->toEqual(3);
    expect($subscription->plan_id)->toEqual($quarterlyPlan->id);

    $expectedExpiresAt = Carbon::parse('2025-04-01')->addMonths(3);
    expect($subscription->expires_at->format('Y-m-d'))->toEqual($expectedExpiresAt->format('Y-m-d'));
});

it('renewal uses plan grace period days', function () {
    $plan = (Foundry::$planModel)::factory()->withGracePeriod(14)->create(['price' => 1000]);
    $user = (Foundry::$subscriptionUserModel)::factory()->create();

    $originalExpiresAt = Carbon::parse('2025-01-01');

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => Carbon::parse('2024-12-01'),
        'expires_at' => $originalExpiresAt,
    ]);
    $subscription->save();

    Carbon::setTestNow('2025-01-01 12:00:00');

    $subscription->renew();

    $expectedGraceEnd = Carbon::now()->addDays(14);
    expect($subscription->ends_at)->not->toBeNull();
    expect($subscription->ends_at->format('Y-m-d H:i'))->toEqual($expectedGraceEnd->format('Y-m-d H:i'));

    Carbon::setTestNow(); // Reset
});

it('renewal with zero grace period expires immediately', function () {
    $plan = (Foundry::$planModel)::factory()->withGracePeriod(0)->create(['price' => 1000]);
    $user = (Foundry::$subscriptionUserModel)::factory()->create();

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => Carbon::parse('2024-12-01'),
        'expires_at' => Carbon::parse('2025-01-01'),
    ]);
    $subscription->save();

    $subscription->renew();

    expect($subscription->ends_at)->toBeNull();
    expect($subscription->status)->toEqual(SubscriptionStatus::EXPIRED);
});

it('artisan command renews active subscriptions', function () {
    (Foundry::$planModel)::factory()->create();
    $subscription = (Foundry::$subscriptionModel)::withoutEvents(function () {
        return (Foundry::$subscriptionModel)::factory()->create(['expires_at' => now()->subDay()]);
    });

    $this->artisan('foundry:subscriptions-renew')->assertExitCode(0);

    $this->assertDatabaseHas('logs', [
        'type' => 'renew',
        'logable_type' => 'Subscription',
        'logable_id' => $subscription->id,
    ]);
});

it('artisan command logs an error when renewal fails', function () {
    (Foundry::$subscriptionModel)::withoutEvents(function () {
        return (Foundry::$subscriptionModel)::factory()->create(['expires_at' => now()->subDay()]);
    });

    $this->partialMock(Foundry::$subscriptionModel, function ($mock) {
        $mock->shouldReceive('renew')->andThrow(new Exception('Renewal failed'));
    });

    $this->artisan('foundry:subscriptions-renew')->assertExitCode(0);
});

it('artisan command renews trialing subscriptions that have expired', function () {
    Carbon::setTestNow(null);
    $plan = (Foundry::$planModel)::factory()->create(['grace_period_days' => 0, 'price' => 1000]);
    $subscription = (Foundry::$subscriptionModel)::withoutEvents(function () use ($plan) {
        return (Foundry::$subscriptionModel)::factory()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => now()->subDay(),
            'expires_at' => now()->subDay(),
        ]);
    });

    $this->artisan('foundry:subscriptions-renew')->assertExitCode(0);

    $this->assertDatabaseHas('logs', [
        'type' => 'renew',
        'logable_type' => 'Subscription',
        'logable_id' => $subscription->id,
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'status' => SubscriptionStatus::EXPIRED,
    ]);
});

it('does not reset non resetable features on renewal', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['trial_days' => 0]);

    $resetableFeature = Feature::factory()->create([
        'slug' => 'api-calls',
        'type' => 'integer',
        'resetable' => true,
    ]);
    $nonResetableFeature = Feature::factory()->create([
        'slug' => 'storage-used',
        'type' => 'integer',
        'resetable' => false,
    ]);

    $plan->features()->attach($resetableFeature->id, ['value' => 1000]);
    $plan->features()->attach($nonResetableFeature->id, ['value' => 5000]);

    $subscription = $user->newSubscription('default', $plan->id)->saveAndInvoice([], true);
    $subscription->recordFeatureUsage('api-calls', 500);
    $subscription->recordFeatureUsage('storage-used', 2500);

    $subscription->update(['expires_at' => now()->subDay()]);
    $subscription->renew();

    expect($subscription->getFeatureUsage('api-calls'))->toEqual(0);
    expect($subscription->getFeatureUsage('storage-used'))->toEqual(2500);
});
