<?php

uses(\Foundry\Tests\TestCase::class);

beforeEach(function () {
    
    // Set a global setup fee for testing
    \Illuminate\Support\Facades\Config::set('foundry.subscription.setup_fee', 15000);
});

it('setup fee is charged on first ever subscription', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 40000,
    'trial_days' => 0,
    'setup_fee' => null, // Should use global 15000
    ]);
    
    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();
    
    $invoice = $subscription->fresh()->latestInvoice;
    $this->assertNotNull($invoice);
    
    // Items: Plan fee (40000) + Admission Fee (15000)
    $this->assertEquals(55000, (float) $invoice->sub_total);
    $this->assertCount(2, $invoice->line_items);
    
    $this->assertEquals('Admission Fee', $invoice->line_items[1]['title']);
    $this->assertEquals(15000, (float) $invoice->line_items[1]['price']);
});

it('plan specific setup fee overrides global', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 40000,
    'trial_days' => 0,
    'setup_fee' => 20000,
    ]);
    
    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();
    
    $invoice = $subscription->fresh()->latestInvoice;
    $this->assertEquals(60000, (float) $invoice->sub_total);
    $this->assertEquals(20000, (float) $invoice->line_items[1]['price']);
});

it('setup fee can be disabled per plan', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create([
    'price' => 40000,
    'trial_days' => 0,
    'setup_fee' => 0.0, // Explicitly disabled
    ]);
    
    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();
    
    $invoice = $subscription->fresh()->latestInvoice;
    $this->assertEquals(40000, (float) $invoice->sub_total);
    $this->assertCount(1, $invoice->line_items);
});

it('setup fee is not charged on second subscription', function () {
    $user = \App\Models\User::factory()->create();
    
    // First subscription
    $plan1 = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 10000, 'trial_days' => 0]);
    $user->newSubscription('default', $plan1)->saveAndInvoice();
    
    // Second subscription
    $plan2 = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 20000, 'trial_days' => 0]);
    $subscription2 = $user->newSubscription('premium', $plan2);
    $subscription2->saveAndInvoice();
    
    $invoice2 = $subscription2->fresh()->latestInvoice;
    $this->assertEquals(20000, (float) $invoice2->sub_total);
    $this->assertCount(1, $invoice2->line_items);
});

it('setup fee is not charged on plan swap', function () {
    $user = \App\Models\User::factory()->create();
    $plan1 = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 40000, 'trial_days' => 0]);
    $subscription = $user->newSubscription('default', $plan1);
    $subscription->saveAndInvoice();
    
    // Verify first invoice has setup fee
    $this->assertEquals(55000, (float) $subscription->fresh()->latestInvoice->sub_total);
    
    // Swap to another plan
    $plan2 = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 60000, 'trial_days' => 0]);
    $subscription->swap($plan2->id);
    
    // Verify swap invoice DOES NOT have setup fee
    $swapInvoice = $subscription->invoices()->orderBy('id', 'desc')->first();
    $this->assertEquals(60000, (float) $swapInvoice->sub_total);
    $this->assertCount(1, $swapInvoice->line_items);
});

it('setup fee is not charged on renewal', function () {
    $user = \App\Models\User::factory()->create();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 40000, 'trial_days' => 0]);
    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();
    
    // Renew subscription
    $subscription->renew();
    
    $renewalInvoice = $subscription->invoices()->orderBy('id', 'desc')->first();
    $this->assertEquals(40000, (float) $renewalInvoice->sub_total);
    $this->assertCount(1, $renewalInvoice->line_items);
});
