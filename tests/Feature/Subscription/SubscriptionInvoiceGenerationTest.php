<?php

uses(\Foundry\Tests\TestCase::class);

it('does not generate invoice for free plan', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 0,
    'trial_days' => 0,
    ]);
    
    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();
    
    $this->assertCount(0, $subscription->invoices()->get());
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::ACTIVE, $subscription->status);
});

it('does not generate invoice for plan with negative price', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => -10,
    'trial_days' => 0,
    ]);
    
    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();
    
    $this->assertCount(0, $subscription->invoices()->get());
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::ACTIVE, $subscription->status);
});

it('does not generate invoice for free forever', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 1000,
    'trial_days' => 0,
    ]);
    
    $subscription = $user->newSubscription('default', $plan);
    $subscription->is_free_forever = true;
    $subscription->saveAndInvoice();
    
    $this->assertCount(0, $subscription->invoices()->get());
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::ACTIVE, $subscription->status);
    $this->assertTrue($subscription->active());
});

it('updates existing pending invoice', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 1000,
    'trial_days' => 0,
    ]);
    
    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();
    
    $subscription->refresh();
    $this->assertCount(1, $subscription->invoices);
    $firstInvoice = $subscription->latestInvoice;
    $this->assertNotNull($firstInvoice, 'Latest invoice should not be null');
    $this->assertTrue($firstInvoice->isPendingPayment());
    
    // Update some metadata to simulate a change that should be reflected in the updated invoice
    $subscription->metadata = ['test' => 'updated'];
    $subscription->save();
    
    // Call generateInvoice again
    $subscription->generateInvoice();
    
    $this->assertCount(1, $subscription->invoices()->get(), 'Should not create a second invoice if the first one is pending');
    $this->assertEquals($firstInvoice->id, $subscription->latestInvoice->id);
});

it('subscribed recognizes free forever', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 1000,
    'trial_days' => 0,
    ]);
    
    $subscription = $user->newSubscription('default', $plan);
    $subscription->is_free_forever = true;
    $subscription->save();
    
    $this->assertTrue($user->subscribed('default'));
});
