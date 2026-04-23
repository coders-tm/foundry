<?php

namespace Foundry\Tests\Feature\Subscription;

use App\Models\User;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

class SubscriptionFreezeTest extends TestCase
{
    public function test_subscription_can_be_frozen_immediately()
    {
        // Create plan and subscription
        $plan = Plan::factory()->create(['price' => 2000]);
        $user = User::factory()->create();
        $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->save();

        $releaseAt = now()->addDays(60);
        $reason = 'Going abroad';

        // Freeze subscription
        $subscription->freeze($releaseAt, $reason, 200);

        $this->assertEquals(SubscriptionStatus::PAUSED, $subscription->status);
        $this->assertNotNull($subscription->frozen_at);
        $this->assertEquals($releaseAt->format('Y-m-d'), $subscription->release_at->format('Y-m-d'));
        $this->assertTrue($subscription->onFreeze());

        // Check log contains the reason
        $log = $subscription->logs()->where('message', 'LIKE', '%frozen%')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($reason, $log->message);
        $this->assertStringContainsString($releaseAt->format('Y-m-d'), $log->message);
    }

    public function test_subscription_can_be_unfrozen()
    {
        $plan = Plan::factory()->create(['price' => 2000]);
        $user = User::factory()->create();
        $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->save();

        // Freeze subscription
        $releaseAt = now()->addDays(30);
        $subscription->freeze($releaseAt, 'Testing');

        $this->assertTrue($subscription->onFreeze());

        // Unfreeze
        $subscription->unfreeze();

        $this->assertFalse($subscription->onFreeze());
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->frozen_at);
        $this->assertNull($subscription->release_at);
        $this->assertNull($subscription->freeze_reason);
        $this->assertNull($subscription->freeze_fee);
    }

    public function test_freeze_extends_contract_end_date()
    {
        // Create contract plan
        $plan = Plan::factory()->create([
            'price' => 10000,
            'interval' => 'month',
            'interval_count' => 1,
            'is_contract' => true,
            'contract_cycles' => 12,
        ]);

        $user = User::factory()->create();
        $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->expires_at = now()->addYear();
        $subscription->save();

        $originalExpiresAt = $subscription->expires_at->copy();
        $frozenAt = now();

        // Freeze for 60 days
        $releaseAt = now()->addDays(60);
        $subscription->freeze($releaseAt);

        // Verify it's frozen
        $this->assertTrue($subscription->onFreeze());

        // Manually set frozen_at to 60 days ago to simulate passage of time
        $subscription->frozen_at = $frozenAt->copy()->subDays(60);
        $subscription->save();
        $subscription->refresh();

        // Unfreeze
        $subscription->unfreeze();

        // Contract end should be extended by 60 days
        $this->assertEquals(
            $originalExpiresAt->addDays(60)->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d'),
            'Contract end date should be extended by freeze duration'
        );
    }

    public function test_cannot_freeze_if_already_frozen()
    {
        $plan = Plan::factory()->create(['price' => 2000]);
        $user = User::factory()->create();
        $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->save();

        // Freeze subscription
        $subscription->freeze(now()->addDays(30));

        $this->assertTrue($subscription->onFreeze());

        // Try to freeze again
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Subscription cannot be frozen at this time');

        $subscription->freeze(now()->addDays(60));
    }

    public function test_cannot_freeze_canceled_subscription()
    {
        $plan = Plan::factory()->create(['price' => 2000]);
        $user = User::factory()->create();
        $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
        $subscription->status = SubscriptionStatus::CANCELED;
        $subscription->canceled_at = now();
        $subscription->save();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Subscription cannot be frozen at this time');

        $subscription->freeze(now()->addDays(30));
    }

    public function test_frozen_scope_returns_only_frozen_subscriptions()
    {
        $plan = Plan::factory()->create();
        $user = User::factory()->create();

        // Create active subscription
        $activeSubscription = $user->newSubscription('active', $plan->id)->saveWithoutInvoice();
        $activeSubscription->status = SubscriptionStatus::ACTIVE;
        $activeSubscription->save();

        // Create frozen subscription
        $frozenSubscription = $user->newSubscription('frozen', $plan->id)->saveWithoutInvoice();
        $frozenSubscription->status = SubscriptionStatus::PAUSED;
        $frozenSubscription->frozen_at = now();
        $frozenSubscription->release_at = now()->addDays(30);
        $frozenSubscription->save();

        $frozenCount = Subscription::frozen()->count();
        $this->assertEquals(1, $frozenCount);

        $frozen = Subscription::frozen()->first();
        $this->assertEquals($frozenSubscription->id, $frozen->id);
    }

    public function test_due_for_unfreeze_scope()
    {
        $plan = Plan::factory()->create();
        $user = User::factory()->create();

        // Create frozen subscription due for unfreeze
        $dueSubscription = $user->newSubscription('due', $plan->id)->saveWithoutInvoice();
        $dueSubscription->status = SubscriptionStatus::PAUSED;
        $dueSubscription->frozen_at = now()->subDays(30);
        $dueSubscription->release_at = now()->subDay(); // Past release date
        $dueSubscription->save();

        // Create frozen subscription not yet due
        $notDueSubscription = $user->newSubscription('notdue', $plan->id)->saveWithoutInvoice();
        $notDueSubscription->status = SubscriptionStatus::PAUSED;
        $notDueSubscription->frozen_at = now();
        $notDueSubscription->release_at = now()->addDays(30);
        $notDueSubscription->save();

        $dueCount = Subscription::dueForUnfreeze()->count();
        $this->assertEquals(1, $dueCount);

        $due = Subscription::dueForUnfreeze()->first();
        $this->assertEquals($dueSubscription->id, $due->id);
    }

    public function test_freeze_fee_uses_config_default()
    {
        config(['foundry.subscription.freeze_fee' => 250.00]);

        $plan = Plan::factory()->create(['price' => 2000]);
        $user = User::factory()->create();
        $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->save();

        // Freeze without specifying fee
        $subscription->freeze(now()->addDays(30), 'Testing');

        // Verify invoice was created with the config fee
        $invoice = $subscription->invoices()->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(250.00, $invoice->grand_total);
    }

    public function test_freeze_can_be_disabled_via_config()
    {
        config(['foundry.subscription.allow_freeze' => false]);

        $plan = Plan::factory()->create(['price' => 2000]);
        $user = User::factory()->create();
        $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->save();

        $this->assertFalse($subscription->canFreeze());

        $this->expectException(\LogicException::class);
        $subscription->freeze(now()->addDays(30));
    }
}
