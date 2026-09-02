<?php

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('save and invoice returns subscription instance', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'status' => SubscriptionStatus::ACTIVE,
        'plan_id' => $plan->id,
        'starts_at' => now(),
        'expires_at' => now()->addMonth(),
    ]);

    // Call saveAndInvoice and verify it returns the subscription instance
    $result = $subscription->saveAndInvoice();

    expect($result)->toBeInstanceOf(Foundry::$subscriptionModel);
    expect($result->id)->toEqual($subscription->id);
    expect($result->exists)->toBeTrue();
});

it('save and invoice generates invoice when not on trial', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'status' => SubscriptionStatus::ACTIVE,
        'plan_id' => $plan->id,
        'starts_at' => now(),
        'expires_at' => now()->addMonth(),
    ]);

    // Save and invoice
    $result = $subscription->saveAndInvoice()->refresh();

    // Verify subscription was saved
    expect($result->exists)->toBeTrue();

    // Verify invoice was generated
    $invoice = $result->latestInvoice;
    expect($invoice)->not->toBeNull();
    expect($invoice)->toBeInstanceOf(Order::class);
});

it('save and invoice skips invoice generation when on trial', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'status' => SubscriptionStatus::TRIALING,
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addDays(14),
        'starts_at' => now(),
        'expires_at' => now()->addMonth(),
    ]);

    // Save and invoice
    $result = $subscription->saveAndInvoice()->refresh();

    // Verify subscription was saved
    expect($result->exists)->toBeTrue();
    expect($result->onTrial())->toBeTrue();

    // Verify invoice was NOT generated (because on trial)
    $invoice = $result->latestInvoice;
    expect($invoice)->toBeNull();
});

it('save and invoice forces invoice generation when on trial', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'status' => SubscriptionStatus::TRIALING,
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addDays(14),
        'starts_at' => now(),
        'expires_at' => now()->addMonth(),
    ]);

    // Save and invoice with force=true
    $result = $subscription->saveAndInvoice([], true)->refresh();

    // Verify subscription was saved
    expect($result->exists)->toBeTrue();
    expect($result->onTrial())->toBeTrue();

    // Verify invoice WAS generated (because forced)
    $invoice = $result->latestInvoice;
    expect($invoice)->not->toBeNull();
    expect($invoice)->toBeInstanceOf(Order::class);
});

it('save and invoice can be chained', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create(['price' => 1000]);

    $subscription = new (Foundry::$subscriptionModel)([
        'user_id' => $user->id,
        'type' => 'default',
        'status' => SubscriptionStatus::ACTIVE,
        'plan_id' => $plan->id,
        'starts_at' => now(),
        'expires_at' => now()->addMonth(),
    ]);

    // Test method chaining
    $result = $subscription
        ->saveAndInvoice()
        ->refresh();

    expect($result)->toBeInstanceOf(Foundry::$subscriptionModel);
    expect($result->exists)->toBeTrue();
});

it('save and invoice preserves trialing status from new subscription', function () {
    // 1. Setup User and Plan with trial days
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'trial_days' => 14,
        'interval' => 'month',
        'price' => 1000,
    ]);

    // 2. Create new subscription (initializes as TRIALING)
    $subscription = $user->newSubscription('default', $plan);
    expect($subscription->status)->toEqual(SubscriptionStatus::TRIALING);
    expect($subscription->onTrial())->toBeTrue();

    // 3. Call saveAndInvoice
    // This should trigger generateInvoice, but due to our fix, it should return early
    $subscription->saveAndInvoice();

    // 4. Assert Status is still TRIALING
    expect($subscription->status)->toEqual(SubscriptionStatus::TRIALING);

    // 5. Assert No Invoice Generated
    expect($subscription->invoices()->get())->toHaveCount(0);
});
