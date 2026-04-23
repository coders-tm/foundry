<?php

namespace Foundry\Tests\Feature\Subscription;

use App\Models\User;
use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Events\SubscriptionExpired;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Notifications\SubscriptionExpiredNotification;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class SubscriptionGracePeriodTest extends TestCase
{
    public function test_renewal_expires_subscription_on_wallet_charge_failure_when_no_grace_period()
    {
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
        \Foundry\Models\Notification::create([
            'label' => 'Subscription Expired',
            'subject' => 'Your subscription has expired',
            'content' => 'Please renew your subscription.',
            'type' => 'user:subscription-expired',
            'is_default' => true,
        ]);

        Event::fake([SubscriptionExpired::class]);
        Notification::fake();

        // Act: Attempt to renew
        $subscription->renew();

        // Assert:
        // 1. Subscription status should be EXPIRED
        $this->assertEquals(SubscriptionStatus::EXPIRED, $subscription->fresh()->status, 'Subscription status should be EXPIRED after failed wallet charge with no grace period.');

        // 2. SubscriptionExpired event should be dispatched
        Event::assertDispatched(SubscriptionExpired::class);

        // 3. Notification should be sent
        Notification::assertSentTo(
            [$subscription->user],
            SubscriptionExpiredNotification::class
        );
    }

    public function test_renewal_enters_grace_period_on_wallet_charge_failure_when_grace_period_exists()
    {
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
        Notification::fake();

        // Act: Attempt to renew
        $subscription->renew();

        // Assert:
        // 1. Subscription status should NOT be EXPIRED (effectively ACTIVE with future ends_at)
        // Note: The renew method (via setPeriod logic) sets ends_at to grace period end.
        $this->assertNotEquals(SubscriptionStatus::EXPIRED, $subscription->fresh()->status, 'Subscription status should NOT be EXPIRED when grace period exists.');

        // 2. Ends_at should be in the future (grace period end)
        $this->assertTrue($subscription->fresh()->ends_at->isFuture(), 'Subscription ends_at should be in the future (grace period).');

        // 3. SubscriptionExpired event should NOT be dispatched
        Event::assertNotDispatched(SubscriptionExpired::class);

        // 4. Notification should NOT be sent (Expired notification)
        Notification::assertNotSentTo(
            [$subscription->user],
            SubscriptionExpiredNotification::class
        );
    }
}
