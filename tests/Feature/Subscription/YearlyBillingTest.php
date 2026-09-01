<?php

uses(\Foundry\Tests\TestCase::class);

use Foundry\Events\ResetFeatureUsages;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

afterEach(function () {
    Carbon::setTestNow(null);
});

it('plan can store yearly fee', function () {
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
    ]);

    $this->assertEquals(1000, $plan->price);
    $this->assertEquals(10000, $plan->yearly_fee);
});

it('plan yearly price formatted accessor', function () {
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
    ]);

    $this->assertNotNull($plan->yearly_price_formatted);
    $this->assertStringContainsString('$10,000.00', $plan->yearly_price_formatted);
});

it('plan yearly fee is calculated from price when no yearly fee set', function () {
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'yearly_fee' => null,
    ]);

    $this->assertEquals(1000 * 12, $plan->yearly_fee);
});

it('plan is free checks yearly fee', function () {
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 0,
        'yearly_fee' => 100,
    ]);

    $this->assertFalse($plan->isFree());
});

it('plan is free when both prices zero', function () {
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 0,
        'yearly_fee' => 0,
    ]);

    $this->assertTrue($plan->isFree());
});

it('plan is free when yearly fee null and price zero', function () {
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 0,
        'yearly_fee' => null,
    ]);

    $this->assertTrue($plan->isFree());
});

it('yearly fee is included in currency fields', function () {
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
    ]);

    $this->assertContains('yearly_fee', $plan->getCurrencyFields());
});

it('new subscription with yearly billing sets correct interval', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertEquals('year', $subscription->billing_interval);
    $this->assertEquals(1, $subscription->billing_interval_count);
});

it('new subscription with yearly billing sets credit resets at', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertNotNull($subscription->credit_resets_at);
    $this->assertTrue($subscription->credit_resets_at->isFuture());
});

it('new subscription with yearly billing credit resets at matches plan interval', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $expectedCreditReset = Carbon::parse('2027-06-01 12:00:00');
    $this->assertTrue(
        $subscription->credit_resets_at->eq($expectedCreditReset),
        "Expected {$expectedCreditReset} but got {$subscription->credit_resets_at}"
    );
});

it('new subscription with monthly billing sets credit resets at', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertNotNull($subscription->credit_resets_at);
});

it('new subscription monthly billing uses plan interval', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertEquals('month', $subscription->billing_interval);
});

it('yearly subscription expires at is one year from now', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertTrue(
        $subscription->expires_at->eq(Carbon::parse('2027-06-01 12:00:00')),
        "Expected 2027-06-01 12:00:00 but got {$subscription->expires_at}"
    );
});

it('yearly subscription upcoming invoice uses yearly fee', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $invoice = $subscription->upcomingInvoice(true);

    $this->assertNotNull($invoice);
    $this->assertCount(1, $invoice->line_items);
    $this->assertEquals(10000, $invoice->line_items[0]['price']);
    $this->assertEquals(10000, $invoice->line_items[0]['total']);
});

it('yearly subscription upcoming invoice uses year interval', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $invoice = $subscription->upcomingInvoice(true);

    $this->assertNotNull($invoice);
    $this->assertStringContainsString('year', $invoice->line_items[0]['title']);
});

it('monthly subscription upcoming invoice uses plan price', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'month',
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $invoice = $subscription->upcomingInvoice(true);

    $this->assertNotNull($invoice);
    $this->assertEquals(1000, $invoice->line_items[0]['price']);
    $this->assertStringContainsString('month', $invoice->line_items[0]['title']);
});

it('yearly subscription status response includes credit resets at', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $response = $subscription->status()->toResponse();

    $this->assertArrayHasKey('credit_resets_at', $response);
    $this->assertNotNull($response['credit_resets_at']);
});

it('monthly subscription status response credit resets at is not null', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $response = $subscription->status()->toResponse();

    $this->assertArrayHasKey('credit_resets_at', $response);
    $this->assertNotNull($response['credit_resets_at']);
});

it('advance credit resets at moves to next plan interval', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertTrue(
        $subscription->credit_resets_at->eq(Carbon::parse('2027-06-01 12:00:00'))
    );

    $subscription->advanceCreditResetsAt();

    $this->assertTrue(
        $subscription->credit_resets_at->eq(Carbon::parse('2028-06-01 12:00:00')),
        "Expected 2028-06-01 12:00:00 but got {$subscription->credit_resets_at}"
    );
});

it('swap resets to plan defaults and updates credit resets at', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    $newPlan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 2000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();
    $subscription->paymentConfirmation();

    $this->assertEquals('year', $subscription->billing_interval);
    $this->assertNotNull($subscription->credit_resets_at);

    $subscription->swap($newPlan->id, false);

    $this->assertEquals($newPlan->id, $subscription->plan_id);
    $this->assertEquals('month', $subscription->billing_interval);
    $this->assertNotNull($subscription->credit_resets_at);
});

it('yearly billing creates subscription with correct period', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertTrue(
        $subscription->starts_at->eq(Carbon::parse('2026-06-01 12:00:00')),
        "starts_at: expected 2026-06-01 12:00:00 got {$subscription->starts_at}"
    );
    $this->assertTrue(
        $subscription->expires_at->eq(Carbon::parse('2027-06-01 12:00:00')),
        "expires_at: expected 2027-06-01 12:00:00 got {$subscription->expires_at}"
    );
    $this->assertEquals('year', $subscription->billing_interval);
    $this->assertEquals(1, $subscription->billing_interval_count);
    $this->assertTrue(
        $subscription->credit_resets_at->eq(Carbon::parse('2027-06-01 12:00:00')),
        "credit_resets_at: expected 2027-06-01 12:00:00 got {$subscription->credit_resets_at}"
    );
});

it('yearly subscription generates invoice with yearly price', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 12000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice([], true);

    $subscription->refresh();
    $this->assertNotNull($subscription->latestInvoice);
    $invoice = $subscription->latestInvoice;

    $this->assertEquals(12000, $invoice->line_items[0]['price']);
    $this->assertEquals(12000, $invoice->sub_total);
});

it('monthly subscription generates invoice with monthly price', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 12000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice([], true);

    $subscription->refresh();
    $this->assertNotNull($subscription->latestInvoice);
    $invoice = $subscription->latestInvoice;

    $this->assertEquals(1000, $invoice->line_items[0]['price']);
    $this->assertEquals(1000, $invoice->sub_total);
});

it('credit resets at is set even without yearly fee', function () {
    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => null,
        'interval' => 'year',
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertEquals('year', $subscription->billing_interval);
    $this->assertNotNull(
        $subscription->credit_resets_at,
        'credit_resets_at should be set for yearly billing even without yearly_fee'
    );
});

it('yearly billing with trial sets credit resets at correctly', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 14,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertEquals('year', $subscription->billing_interval);
    $this->assertTrue($subscription->onTrial());
    $this->assertNotNull($subscription->credit_resets_at);
    $this->assertTrue(
        $subscription->credit_resets_at->eq(Carbon::parse('2027-06-15 12:00:00')),
        "Expected 2027-06-15 12:00:00 but got {$subscription->credit_resets_at}"
    );
});

it('payment keeps credit resets at intact', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice([], true);

    $this->assertNotNull($subscription->credit_resets_at);

    $originalCreditReset = $subscription->credit_resets_at->copy();

    $subscription->paymentConfirmation();

    $subscription->refresh();

    $this->assertNotNull($subscription->credit_resets_at);
    $this->assertTrue(
        $subscription->credit_resets_at->eq($originalCreditReset),
        'paymentConfirmation should preserve credit_resets_at'
    );
});

it('renew advances credit resets at', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
        'grace_period_days' => 7,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();
    $subscription->paymentConfirmation();

    $originalCreditReset = $subscription->credit_resets_at->copy();

    Carbon::setTestNow(Carbon::parse('2027-06-01 12:00:00'));

    $subscription->expires_at = Carbon::now()->subDay();
    $subscription->save();

    $subscription->renew();

    $this->assertNotNull($subscription->credit_resets_at);
    $this->assertTrue(
        $subscription->credit_resets_at->gt($originalCreditReset),
        'credit_resets_at should have advanced after renewal'
    );
});

it('early renew extends expires_at for active subscription without resetting credits', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();
    $subscription->paymentConfirmation();

    $this->assertTrue($subscription->active());
    $this->assertTrue($subscription->expires_at->eq(Carbon::parse('2026-07-01 12:00:00')));

    $subscription->renew(false);

    $subscription->refresh();
    $this->assertTrue($subscription->expires_at->eq(Carbon::parse('2026-08-01 12:00:00')),
        "expires_at: expected 2026-08-01 12:00:00 got {$subscription->expires_at}"
    );
    $this->assertEquals(\Foundry\Contracts\SubscriptionStatus::ACTIVE, $subscription->status);
});

it('early renew preserves feature usage for active subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();
    $subscription->paymentConfirmation();

    $feature = $subscription->features()->create([
        'slug' => 'api-calls',
        'label' => 'API Calls',
        'type' => 'integer',
        'resetable' => 1,
        'value' => 1000,
        'used' => 0,
    ]);

    $subscription->recordFeatureUsage('api-calls', 500);
    $this->assertEquals(500, $subscription->getFeatureUsage('api-calls'));

    $subscription->renew(false);

    $subscription->refresh();
    $this->assertEquals(500, $subscription->getFeatureUsage('api-calls'),
        'Credits should NOT be reset for active subscription on early renew'
    );
});

it('early renew does not advance credit_resets_at for active subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();
    $subscription->paymentConfirmation();

    $originalCreditReset = $subscription->credit_resets_at->copy();

    $subscription->renew(false);

    $subscription->refresh();
    $this->assertTrue(
        $subscription->credit_resets_at->eq($originalCreditReset),
        'credit_resets_at should NOT change for active subscription on early renew'
    );
});

it('expired subscription renew resets credits and advances credit_resets_at', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();
    $subscription->paymentConfirmation();

    $feature = $subscription->features()->create([
        'slug' => 'api-calls',
        'label' => 'API Calls',
        'type' => 'integer',
        'resetable' => 1,
        'value' => 1000,
        'used' => 0,
    ]);

    $subscription->recordFeatureUsage('api-calls', 500);
    $originalCreditReset = $subscription->credit_resets_at->copy();

    // Expire the subscription
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));
    $subscription->update([
        'status' => \Foundry\Contracts\SubscriptionStatus::EXPIRED,
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $this->assertTrue($subscription->fresh()->expired());

    $subscription->renew(false);

    $subscription->refresh();
    $this->assertEquals(0, $subscription->getFeatureUsage('api-calls'),
        'Credits should be reset for expired subscription'
    );
    $this->assertNotNull($subscription->credit_resets_at);
    $this->assertTrue(
        $subscription->credit_resets_at->gt($originalCreditReset),
        'credit_resets_at should advance after expired renewal'
    );
});

it('early renew clears ends_at and trial_ends_at', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (\Foundry\Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (\Foundry\Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 14,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $this->assertTrue($subscription->onTrial());
    $this->assertNotNull($subscription->trial_ends_at);

    $subscription->renew(false);

    $subscription->refresh();
    $this->assertNull($subscription->ends_at, 'ends_at should be cleared');
    $this->assertNull($subscription->trial_ends_at, 'trial_ends_at should be cleared');
});
