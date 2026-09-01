<?php

uses(\Foundry\Tests\TestCase::class);

it('save and invoice returns subscription instance', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create(['price' => 1000]);
    
    $subscription = new (\Foundry\Foundry::$subscriptionModel)([
    'user_id' => $user->id,
    'type' => 'default',
    'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE,
    'plan_id' => $plan->id,
    'starts_at' => now(),
    'expires_at' => now()->addMonth(),
    ]);
    
    // Call saveAndInvoice and verify it returns the subscription instance
    $result = $subscription->saveAndInvoice();
    
    $this->assertInstanceOf(\Foundry\Foundry::$subscriptionModel, $result);
    $this->assertEquals($subscription->id, $result->id);
    $this->assertTrue($result->exists);
});

it('save and invoice generates invoice when not on trial', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create(['price' => 1000]);
    
    $subscription = new (\Foundry\Foundry::$subscriptionModel)([
    'user_id' => $user->id,
    'type' => 'default',
    'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE,
    'plan_id' => $plan->id,
    'starts_at' => now(),
    'expires_at' => now()->addMonth(),
    ]);
    
    // Save and invoice
    $result = $subscription->saveAndInvoice()->refresh();
    
    // Verify subscription was saved
    $this->assertTrue($result->exists);
    
    // Verify invoice was generated
    $invoice = $result->latestInvoice;
    $this->assertNotNull($invoice);
    $this->assertInstanceOf(\Foundry\Models\Order::class, $invoice);
});

it('save and invoice skips invoice generation when on trial', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create(['price' => 1000]);
    
    $subscription = new (\Foundry\Foundry::$subscriptionModel)([
    'user_id' => $user->id,
    'type' => 'default',
    'status' => \Foundry\Contracts\SubscriptionStatus::TRIALING,
    'plan_id' => $plan->id,
    'trial_ends_at' => now()->addDays(14),
    'starts_at' => now(),
    'expires_at' => now()->addMonth(),
    ]);
    
    // Save and invoice
    $result = $subscription->saveAndInvoice()->refresh();
    
    // Verify subscription was saved
    $this->assertTrue($result->exists);
    $this->assertTrue($result->onTrial());
    
    // Verify invoice was NOT generated (because on trial)
    $invoice = $result->latestInvoice;
    $this->assertNull($invoice);
});

it('save and invoice forces invoice generation when on trial', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create(['price' => 1000]);
    
    $subscription = new (\Foundry\Foundry::$subscriptionModel)([
    'user_id' => $user->id,
    'type' => 'default',
    'status' => \Foundry\Contracts\SubscriptionStatus::TRIALING,
    'plan_id' => $plan->id,
    'trial_ends_at' => now()->addDays(14),
    'starts_at' => now(),
    'expires_at' => now()->addMonth(),
    ]);
    
    // Save and invoice with force=true
    $result = $subscription->saveAndInvoice([], true)->refresh();
    
    // Verify subscription was saved
    $this->assertTrue($result->exists);
    $this->assertTrue($result->onTrial());
    
    // Verify invoice WAS generated (because forced)
    $invoice = $result->latestInvoice;
    $this->assertNotNull($invoice);
    $this->assertInstanceOf(\Foundry\Models\Order::class, $invoice);
});

it('save and invoice can be chained', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create(['price' => 1000]);
    
    $subscription = new (\Foundry\Foundry::$subscriptionModel)([
    'user_id' => $user->id,
    'type' => 'default',
    'status' => \Foundry\Contracts\SubscriptionStatus::ACTIVE,
    'plan_id' => $plan->id,
    'starts_at' => now(),
    'expires_at' => now()->addMonth(),
    ]);
    
    // Test method chaining
    $result = $subscription
    ->saveAndInvoice()
    ->refresh();
    
    $this->assertInstanceOf(\Foundry\Foundry::$subscriptionModel, $result);
    $this->assertTrue($result->exists);
});

it('save and invoice preserves trialing status from new subscription', function () {
    // 1. Setup User and Plan with trial days
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
    'trial_days' => 14,
    'interval' => 'month',
    'price' => 1000,
    ]);
    
    // 2. Create new subscription (initializes as TRIALING)
    $subscription = $user->newSubscription('default', $plan);
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::TRIALING, $subscription->status);
    $this->assertTrue($subscription->onTrial());
    
    // 3. Call saveAndInvoice
    // This should trigger generateInvoice, but due to our fix, it should return early
    $subscription->saveAndInvoice();
    
    // 4. Assert Status is still TRIALING
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::TRIALING, $subscription->status, 'Subscription status should remain TRIALING');
    
    // 5. Assert No Invoice Generated
    // Since generateInvoice returns null when on trial, no invoice should be created yet.
    $this->assertCount(0, $subscription->invoices()->get(), 'No invoice should be generated during trial');
});
