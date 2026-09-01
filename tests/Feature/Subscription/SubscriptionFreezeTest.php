<?php

uses(\Foundry\Tests\TestCase::class);

it('subscription can be frozen immediately', function () {
    // Create plan and subscription
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 2000]);
    $user = \App\Models\User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = \Foundry\Contracts\SubscriptionStatus::ACTIVE;
    $subscription->save();
    
    $releaseAt = now()->addDays(60);
    $reason = 'Going abroad';
    
    // Freeze subscription
    $subscription->freeze($releaseAt, $reason, 200);
    
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::PAUSED, $subscription->status);
    $this->assertNotNull($subscription->frozen_at);
    $this->assertEquals($releaseAt->format('Y-m-d'), $subscription->release_at->format('Y-m-d'));
    $this->assertTrue($subscription->onFreeze());
    
    // Check log contains the reason
    $log = $subscription->logs()->where('message', 'LIKE', '%frozen%')->latest()->first();
    $this->assertNotNull($log);
    $this->assertStringContainsString($reason, $log->message);
    $this->assertStringContainsString($releaseAt->format('Y-m-d'), $log->message);
});

it('subscription can be unfrozen', function () {
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 2000]);
    $user = \App\Models\User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = \Foundry\Contracts\SubscriptionStatus::ACTIVE;
    $subscription->save();
    
    // Freeze subscription
    $releaseAt = now()->addDays(30);
    $subscription->freeze($releaseAt, 'Testing');
    
    $this->assertTrue($subscription->onFreeze());
    
    // Unfreeze
    $subscription->unfreeze();
    
    $this->assertFalse($subscription->onFreeze());
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::ACTIVE, $subscription->status);
    $this->assertNull($subscription->frozen_at);
    $this->assertNull($subscription->release_at);
    $this->assertNull($subscription->freeze_reason);
    $this->assertNull($subscription->freeze_fee);
});

it('freeze extends expires at', function () {
    // Create plan
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 10000,
    'interval' => 'month',
    'interval_count' => 1,
    ]);
    
    $user = \App\Models\User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = \Foundry\Contracts\SubscriptionStatus::ACTIVE;
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
    
    // expires_at should be extended by 60 days
    $this->assertEquals(
    $originalExpiresAt->addDays(60)->format('Y-m-d'),
    $subscription->expires_at->format('Y-m-d'),
    'expires_at should be extended by freeze duration'
    );
});

it('cannot freeze if already frozen', function () {
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 2000]);
    $user = \App\Models\User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = \Foundry\Contracts\SubscriptionStatus::ACTIVE;
    $subscription->save();
    
    // Freeze subscription
    $subscription->freeze(now()->addDays(30));
    
    $this->assertTrue($subscription->onFreeze());
    
    // Try to freeze again
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Subscription cannot be frozen at this time');
    
    $subscription->freeze(now()->addDays(60));
});

it('cannot freeze canceled subscription', function () {
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 2000]);
    $user = \App\Models\User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = \Foundry\Contracts\SubscriptionStatus::CANCELED;
    $subscription->cancels_at = now();
    $subscription->save();
    
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Subscription cannot be frozen at this time');
    
    $subscription->freeze(now()->addDays(30));
});

it('frozen scope returns only frozen subscriptions', function () {
    $plan = \Foundry\Models\Subscription\Plan::factory()->create();
    $user = \App\Models\User::factory()->create();
    
    // Create active subscription
    $activeSubscription = $user->newSubscription('active', $plan->id)->saveWithoutInvoice();
    $activeSubscription->status = \Foundry\Contracts\SubscriptionStatus::ACTIVE;
    $activeSubscription->save();
    
    // Create frozen subscription
    $frozenSubscription = $user->newSubscription('frozen', $plan->id)->saveWithoutInvoice();
    $frozenSubscription->status = \Foundry\Contracts\SubscriptionStatus::PAUSED;
    $frozenSubscription->frozen_at = now();
    $frozenSubscription->release_at = now()->addDays(30);
    $frozenSubscription->save();
    
    $frozenCount = \Foundry\Models\Subscription::frozen()->count();
    $this->assertEquals(1, $frozenCount);
    
    $frozen = \Foundry\Models\Subscription::frozen()->first();
    $this->assertEquals($frozenSubscription->id, $frozen->id);
});

it('due for unfreeze scope', function () {
    $plan = \Foundry\Models\Subscription\Plan::factory()->create();
    $user = \App\Models\User::factory()->create();
    
    // Create frozen subscription due for unfreeze
    $dueSubscription = $user->newSubscription('due', $plan->id)->saveWithoutInvoice();
    $dueSubscription->status = \Foundry\Contracts\SubscriptionStatus::PAUSED;
    $dueSubscription->frozen_at = now()->subDays(30);
    $dueSubscription->release_at = now()->subDay(); // Past release date
    $dueSubscription->save();
    
    // Create frozen subscription not yet due
    $notDueSubscription = $user->newSubscription('notdue', $plan->id)->saveWithoutInvoice();
    $notDueSubscription->status = \Foundry\Contracts\SubscriptionStatus::PAUSED;
    $notDueSubscription->frozen_at = now();
    $notDueSubscription->release_at = now()->addDays(30);
    $notDueSubscription->save();
    
    $dueCount = \Foundry\Models\Subscription::dueForUnfreeze()->count();
    $this->assertEquals(1, $dueCount);
    
    $due = \Foundry\Models\Subscription::dueForUnfreeze()->first();
    $this->assertEquals($dueSubscription->id, $due->id);
});

it('freeze fee uses config default', function () {
    config(['foundry.subscription.freeze_fee' => 250.00]);
    
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 2000]);
    $user = \App\Models\User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = \Foundry\Contracts\SubscriptionStatus::ACTIVE;
    $subscription->save();
    
    // Freeze without specifying fee
    $subscription->freeze(now()->addDays(30), 'Testing');
    
    // Verify invoice was created with the config fee
    $invoice = $subscription->invoices()->latest()->first();
    $this->assertNotNull($invoice);
    $this->assertEquals(250.00, $invoice->grand_total);
});

it('freeze can be disabled via config', function () {
    config(['foundry.subscription.allow_freeze' => false]);
    
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 2000]);
    $user = \App\Models\User::factory()->create();
    $subscription = $user->newSubscription('default', $plan->id)->saveWithoutInvoice();
    $subscription->status = \Foundry\Contracts\SubscriptionStatus::ACTIVE;
    $subscription->save();
    
    $this->assertFalse($subscription->canFreeze());
    
    $this->expectException(\LogicException::class);
    $subscription->freeze(now()->addDays(30));
});
