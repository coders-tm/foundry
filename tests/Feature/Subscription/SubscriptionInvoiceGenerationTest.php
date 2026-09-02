<?php

use App\Models\User;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('does not generate invoice for free plan', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'price' => 0,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();

    expect($subscription->invoices()->get())->toHaveCount(0);
    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
});

it('does not generate invoice for plan with negative price', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'price' => -10,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();

    expect($subscription->invoices()->get())->toHaveCount(0);
    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
});

it('does not generate invoice for free forever', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'price' => 1000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->is_free_forever = true;
    $subscription->saveAndInvoice();

    expect($subscription->invoices()->get())->toHaveCount(0);
    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
    expect($subscription->active())->toBeTrue();
});

it('updates existing pending invoice', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'price' => 1000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();

    $subscription->refresh();
    expect($subscription->invoices)->toHaveCount(1);
    $firstInvoice = $subscription->latestInvoice;
    expect($firstInvoice)->not->toBeNull();
    expect($firstInvoice->isPendingPayment())->toBeTrue();

    // Update some metadata to simulate a change that should be reflected in the updated invoice
    $subscription->metadata = ['test' => 'updated'];
    $subscription->save();

    // Call generateInvoice again
    $subscription->generateInvoice();

    expect($subscription->invoices()->get())->toHaveCount(1);
    expect($subscription->latestInvoice->id)->toEqual($firstInvoice->id);
});

it('subscribed recognizes free forever', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'price' => 1000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->is_free_forever = true;
    $subscription->save();

    expect($user->subscribed('default'))->toBeTrue();
});
