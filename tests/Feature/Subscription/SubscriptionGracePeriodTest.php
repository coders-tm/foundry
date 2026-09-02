<?php

use App\Models\User;
use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Events\SubscriptionExpired;
use Foundry\Models\Notification;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Notifications\SubscriptionExpiredNotification;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

uses(TestCase::class);

it('renewal expires subscription on wallet charge failure when no grace period', function () {
    // Mock configuration to enable auto charge and no grace period
    Config::set('foundry.wallet.auto_charge_on_renewal', true);
    Config::set('foundry.subscription.grace_period_days', 0);

    // Arrange: Create a user with 0 wallet balance (will fail charge)
    $user = User::factory()->create();

    // Create a plan with 0 grace days (default in factory but ensuring explicit here)
    $plan = Plan::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'grace_period_days' => 0,
    ]);

    // Create a subscription that is due for renewal
    $subscription = new Subscription([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => Carbon::now()->subMonth(),
        'expires_at' => Carbon::now()->subMinutes(1), // Expired
    ]);
    $subscription->save();

    // Seed the notification template
    Notification::create([
        'label' => 'Subscription Expired',
        'subject' => 'Your subscription has expired',
        'content' => 'Please renew your subscription.',
        'type' => 'user:subscription-expired',
        'is_default' => true,
    ]);

    Event::fake([SubscriptionExpired::class]);
    Illuminate\Support\Facades\Notification::fake();

    // Act: Attempt to renew
    $subscription->renew();

    // Assert:
    // 1. Subscription status should be EXPIRED
    expect($subscription->fresh()->status)->toEqual(SubscriptionStatus::EXPIRED);

    // 2. SubscriptionExpired event should be dispatched
    Event::assertDispatched(SubscriptionExpired::class);

    // 3. Notification should be sent
    Illuminate\Support\Facades\Notification::assertSentTo(
        [$subscription->user],
        SubscriptionExpiredNotification::class
    );
});

it('renewal enters grace period on wallet charge failure when grace period exists', function () {
    // Mock configuration to enable auto charge
    Config::set('foundry.wallet.auto_charge_on_renewal', true);

    // Arrange: Create a user with 0 wallet balance (will fail charge)
    $user = User::factory()->create();

    // Create a plan with 3 grace days
    $plan = Plan::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'grace_period_days' => 3,
    ]);

    // Create a subscription that is due for renewal
    $subscription = new Subscription([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'type' => 'default',
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => Carbon::now()->subMonth(),
        'expires_at' => Carbon::now()->subMinutes(1), // Expired
    ]);
    $subscription->save();

    Event::fake([SubscriptionExpired::class]);
    Illuminate\Support\Facades\Notification::fake();

    // Act: Attempt to renew
    $subscription->renew();

    // Assert:
    // 1. Subscription status should NOT be EXPIRED (effectively ACTIVE with future ends_at)
    // Note: The renew method (via setPeriod logic) sets ends_at to grace period end.
    expect($subscription->fresh()->status)->not->toBe(SubscriptionStatus::EXPIRED);

    // 2. Ends_at should be in the future (grace period end)
    expect($subscription->fresh()->ends_at->isFuture())->toBeTrue();

    // 3. SubscriptionExpired event should NOT be dispatched
    Event::assertNotDispatched(SubscriptionExpired::class);

    // 4. Notification should NOT be sent (Expired notification)
    Illuminate\Support\Facades\Notification::assertNotSentTo(
        [$subscription->user],
        SubscriptionExpiredNotification::class
    );
});
