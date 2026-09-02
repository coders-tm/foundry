<?php

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Tests\TestCase;
use Illuminate\Support\Carbon;

uses(TestCase::class);

afterEach(function () {
    Carbon::setTestNow(null);
});

it('plan can store yearly fee', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
    ]);

    expect($plan->price)->toEqual(1000);
    expect($plan->yearly_fee)->toEqual(10000);
});

it('plan yearly price formatted accessor', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
    ]);

    expect($plan->yearly_price_formatted)->not->toBeNull();
    expect($plan->yearly_price_formatted)->toContain('$10,000.00');
});

it('plan yearly fee is calculated from price when no yearly fee set', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'yearly_fee' => null,
    ]);

    expect($plan->yearly_fee)->toEqual(1000 * 12);
});

it('plan is free checks yearly fee', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 0,
        'yearly_fee' => 100,
    ]);

    expect($plan->isFree())->toBeFalse();
});

it('plan is free when both prices zero', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 0,
        'yearly_fee' => 0,
    ]);

    expect($plan->isFree())->toBeTrue();
});

it('plan is free when yearly fee null and price zero', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 0,
        'yearly_fee' => null,
    ]);

    expect($plan->isFree())->toBeTrue();
});

it('yearly fee is included in currency fields', function () {
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
    ]);

    expect($plan->getCurrencyFields())->toContain('yearly_fee');
});

it('new subscription with yearly billing sets correct interval', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->billing_interval)->toEqual('year');
    expect($subscription->billing_interval_count)->toEqual(1);
});

it('new subscription with yearly billing sets credit resets at', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->credit_resets_at)->not->toBeNull();
    expect($subscription->credit_resets_at->isFuture())->toBeTrue();
});

it('new subscription with yearly billing credit resets at matches plan interval', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $expectedCreditReset = Carbon::parse('2027-06-01 12:00:00');
    expect($subscription->credit_resets_at->eq($expectedCreditReset))->toBeTrue();
});

it('new subscription with monthly billing sets credit resets at', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->credit_resets_at)->not->toBeNull();
});

it('new subscription monthly billing uses plan interval', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->billing_interval)->toEqual('month');
});

it('yearly subscription expires at is one year from now', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->expires_at->eq(Carbon::parse('2027-06-01 12:00:00')))->toBeTrue();
});

it('yearly subscription upcoming invoice uses yearly fee', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $invoice = $subscription->upcomingInvoice(true);

    expect($invoice)->not->toBeNull();
    expect($invoice->line_items)->toHaveCount(1);
    expect($invoice->line_items[0]['price'])->toEqual(10000);
    expect($invoice->line_items[0]['total'])->toEqual(10000);
});

it('yearly subscription upcoming invoice uses year interval', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $invoice = $subscription->upcomingInvoice(true);

    expect($invoice)->not->toBeNull();
    expect($invoice->line_items[0]['title'])->toContain('year');
});

it('monthly subscription upcoming invoice uses plan price', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'month',
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $invoice = $subscription->upcomingInvoice(true);

    expect($invoice)->not->toBeNull();
    expect($invoice->line_items[0]['price'])->toEqual(1000);
    expect($invoice->line_items[0]['title'])->toContain('month');
});

it('yearly subscription status response includes credit resets at', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $response = $subscription->status()->toResponse();

    expect($response)->toHaveKey('credit_resets_at');
    expect($response['credit_resets_at'])->not->toBeNull();
});

it('monthly subscription status response credit resets at is not null', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    $response = $subscription->status()->toResponse();

    expect($response)->toHaveKey('credit_resets_at');
    expect($response['credit_resets_at'])->not->toBeNull();
});

it('advance credit resets at moves to next plan interval', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->credit_resets_at->eq(Carbon::parse('2027-06-01 12:00:00')))->toBeTrue();

    $subscription->advanceCreditResetsAt();

    expect($subscription->credit_resets_at->eq(Carbon::parse('2028-06-01 12:00:00')))->toBeTrue();
});

it('swap resets to plan defaults and updates credit resets at', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    $newPlan = (Foundry::$planModel)::factory()->create([
        'price' => 2000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();
    $subscription->paymentConfirmation();

    expect($subscription->billing_interval)->toEqual('year');
    expect($subscription->credit_resets_at)->not->toBeNull();

    $subscription->swap($newPlan->id, false);

    expect($subscription->plan_id)->toEqual($newPlan->id);
    expect($subscription->billing_interval)->toEqual('month');
    expect($subscription->credit_resets_at)->not->toBeNull();
});

it('yearly billing creates subscription with correct period', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->starts_at->eq(Carbon::parse('2026-06-01 12:00:00')))->toBeTrue();
    expect($subscription->expires_at->eq(Carbon::parse('2027-06-01 12:00:00')))->toBeTrue();
    expect($subscription->billing_interval)->toEqual('year');
    expect($subscription->billing_interval_count)->toEqual(1);
    expect($subscription->credit_resets_at->eq(Carbon::parse('2027-06-01 12:00:00')))->toBeTrue();
});

it('yearly subscription generates invoice with yearly price', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 12000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice([], true);

    $subscription->refresh();
    expect($subscription->latestInvoice)->not->toBeNull();
    $invoice = $subscription->latestInvoice;

    expect($invoice->line_items[0]['price'])->toEqual(12000);
    expect($invoice->sub_total)->toEqual(12000);
});

it('monthly subscription generates invoice with monthly price', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 12000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice([], true);

    $subscription->refresh();
    expect($subscription->latestInvoice)->not->toBeNull();
    $invoice = $subscription->latestInvoice;

    expect($invoice->line_items[0]['price'])->toEqual(1000);
    expect($invoice->sub_total)->toEqual(1000);
});

it('credit resets at is set even without yearly fee', function () {
    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => null,
        'interval' => 'year',
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->billing_interval)->toEqual('year');
    expect($subscription->credit_resets_at)->not->toBeNull();
});

it('yearly billing with trial sets credit resets at correctly', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 14,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->billing_interval)->toEqual('year');
    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->credit_resets_at)->not->toBeNull();
    expect($subscription->credit_resets_at->eq(Carbon::parse('2027-06-15 12:00:00')))->toBeTrue();
});

it('payment keeps credit resets at intact', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'yearly_fee' => 10000,
        'interval' => 'year',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice([], true);

    expect($subscription->credit_resets_at)->not->toBeNull();

    $originalCreditReset = $subscription->credit_resets_at->copy();

    $subscription->paymentConfirmation();

    $subscription->refresh();

    expect($subscription->credit_resets_at)->not->toBeNull();
    expect($subscription->credit_resets_at->eq($originalCreditReset))->toBeTrue();
});

it('renew advances credit resets at', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
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

    expect($subscription->credit_resets_at)->not->toBeNull();
    expect($subscription->credit_resets_at->gt($originalCreditReset))->toBeTrue();
});

it('early renew extends expires_at for active subscription without resetting credits', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();
    $subscription->paymentConfirmation();

    expect($subscription->active())->toBeTrue();
    expect($subscription->expires_at->eq(Carbon::parse('2026-07-01 12:00:00')))->toBeTrue();

    $subscription->renew(false);

    $subscription->refresh();
    expect($subscription->expires_at->eq(Carbon::parse('2026-08-01 12:00:00')))->toBeTrue();
    expect($subscription->status)->toEqual(SubscriptionStatus::ACTIVE);
});

it('early renew preserves feature usage for active subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
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
    expect($subscription->getFeatureUsage('api-calls'))->toEqual(500);

    $subscription->renew(false);

    $subscription->refresh();
    expect($subscription->getFeatureUsage('api-calls'))->toEqual(500);
});

it('early renew does not advance credit_resets_at for active subscription', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
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
    expect($subscription->credit_resets_at->eq($originalCreditReset))->toBeTrue();
});

it('expired subscription renew resets credits and advances credit_resets_at', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
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
        'status' => SubscriptionStatus::EXPIRED,
        'expires_at' => Carbon::now()->subDay(),
    ]);

    expect($subscription->fresh()->expired())->toBeTrue();

    $subscription->renew(false);

    $subscription->refresh();
    expect($subscription->getFeatureUsage('api-calls'))->toEqual(0);
    expect($subscription->credit_resets_at)->not->toBeNull();
    expect($subscription->credit_resets_at->gt($originalCreditReset))->toBeTrue();
});

it('early renew clears ends_at and trial_ends_at', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00'));

    $user = (Foundry::$subscriptionUserModel)::factory()->create();
    $plan = (Foundry::$planModel)::factory()->create([
        'price' => 1000,
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 14,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->save();

    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->trial_ends_at)->not->toBeNull();

    $subscription->renew(false);

    $subscription->refresh();
    expect($subscription->ends_at)->toBeNull();
    expect($subscription->trial_ends_at)->toBeNull();
});
