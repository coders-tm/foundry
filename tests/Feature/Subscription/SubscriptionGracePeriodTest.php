<?php

uses(\Foundry\Tests\TestCase::class);

it('renewal expires subscription on wallet charge failure when no grace period', function () {
    // Mock configuration to enable auto charge and no grace period
    \Illuminate\Support\Facades\Config::set('foundry.wallet.auto_charge_on_renewal', true);
    \Illuminate\Support\Facades\Config::set('foundry.subscription.grace_period_days', 0);
    
    // Arrange: Create a user with 0 wallet balance (will fail charge)
    $user = \App\Models\User::factory()->create();
    
    // Create a plan with 0 grace days (default in factory but ensuring explicit here)
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 1000,
    'interval' => 'month',
    'grace_period_days' => 0,
    ]);
    
    // Create a subscription that is due for renewal
    $subscription = new Subscription([
    'user_id' => $user->id,
    'plan_id' => $plan->id,
    'type' => 'default',
    'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE,
    'starts_at' => \Carbon\Carbon::now()->subMonth(),
    'expires_at' => \Carbon\Carbon::now()->subMinutes(1), // Expired
    ]);
    $subscription->save();
    
    // Seed the notification template
    \Foundry\Models\Notification::create([
    'label' => 'Subscription Expired',
    'subject' => 'Your subscription has expired',
    'content' => 'Please renew your subscription.',
    'type' => 'user:subscription-expired',
    'is_default' => true,
    ]);
    
    \Illuminate\Support\Facades\Event::fake([\Foundry\Events\SubscriptionExpired::class]);
    \Illuminate\Support\Facades\Notification::fake();
    
    // Act: Attempt to renew
    $subscription->renew();
    
    // Assert:
    // 1. Subscription status should be EXPIRED
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::EXPIRED, $subscription->fresh()->status, 'Subscription status should be EXPIRED after failed wallet charge with no grace period.');
    
    // 2. SubscriptionExpired event should be dispatched
    \Illuminate\Support\Facades\Event::assertDispatched(\Foundry\Events\SubscriptionExpired::class);
    
    // 3. Notification should be sent
    \Illuminate\Support\Facades\Notification::assertSentTo(
    [$subscription->user],
    \Foundry\Notifications\SubscriptionExpiredNotification::class
    );
});

it('renewal enters grace period on wallet charge failure when grace period exists', function () {
    // Mock configuration to enable auto charge
    \Illuminate\Support\Facades\Config::set('foundry.wallet.auto_charge_on_renewal', true);
    
    // Arrange: Create a user with 0 wallet balance (will fail charge)
    $user = \App\Models\User::factory()->create();
    
    // Create a plan with 3 grace days
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 1000,
    'interval' => 'month',
    'grace_period_days' => 3,
    ]);
    
    // Create a subscription that is due for renewal
    $subscription = new Subscription([
    'user_id' => $user->id,
    'plan_id' => $plan->id,
    'type' => 'default',
    'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE,
    'starts_at' => \Carbon\Carbon::now()->subMonth(),
    'expires_at' => \Carbon\Carbon::now()->subMinutes(1), // Expired
    ]);
    $subscription->save();
    
    \Illuminate\Support\Facades\Event::fake([\Foundry\Events\SubscriptionExpired::class]);
    \Illuminate\Support\Facades\Notification::fake();
    
    // Act: Attempt to renew
    $subscription->renew();
    
    // Assert:
    // 1. Subscription status should NOT be EXPIRED (effectively ACTIVE with future ends_at)
    // Note: The renew method (via setPeriod logic) sets ends_at to grace period end.
    $this->assertNotEquals(\Foundry\Contracts\SubscriptionStatus::EXPIRED, $subscription->fresh()->status, 'Subscription status should NOT be EXPIRED when grace period exists.');
    
    // 2. Ends_at should be in the future (grace period end)
    $this->assertTrue($subscription->fresh()->ends_at->isFuture(), 'Subscription ends_at should be in the future (grace period).');
    
    // 3. SubscriptionExpired event should NOT be dispatched
    \Illuminate\Support\Facades\Event::assertNotDispatched(\Foundry\Events\SubscriptionExpired::class);
    
    // 4. Notification should NOT be sent (Expired notification)
    \Illuminate\Support\Facades\Notification::assertNotSentTo(
    [$subscription->user],
    \Foundry\Notifications\SubscriptionExpiredNotification::class
    );
});
