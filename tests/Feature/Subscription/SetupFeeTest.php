<?php

use App\Models\User;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Config;

uses(TestCase::class);

beforeEach(function () {

    // Set a global setup fee for testing
    Config::set('foundry.subscription.setup_fee', 15000);
});

it('setup fee is charged on first ever subscription', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'price' => 40000,
        'trial_days' => 0,
        'default_interval' => 'month',
        'interval' => 'month',
        'interval_count' => 1,
        'setup_fee' => null, // Should use global 15000
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();

    $invoice = $subscription->fresh()->latestInvoice;
    expect($invoice)->not->toBeNull();

    // Items: Plan fee (40000) + Admission Fee (15000)
    expect($invoice->sub_total)->toEqual(55000);
    expect($invoice->line_items)->toHaveCount(2);

    expect($invoice->line_items[1]['title'])->toEqual('Admission Fee');
    expect($invoice->line_items[1]['price'])->toEqual(15000);
});

it('plan specific setup fee overrides global', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'price' => 40000,
        'trial_days' => 0,
        'default_interval' => 'month',
        'interval' => 'month',
        'interval_count' => 1,
        'setup_fee' => 20000,
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();

    $invoice = $subscription->fresh()->latestInvoice;
    expect($invoice->sub_total)->toEqual(60000);
    expect($invoice->line_items[1]['price'])->toEqual(20000);
});

it('setup fee can be disabled per plan', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'price' => 40000,
        'trial_days' => 0,
        'default_interval' => 'month',
        'interval' => 'month',
        'interval_count' => 1,
        'setup_fee' => 0.0, // Explicitly disabled
    ]);

    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();

    $invoice = $subscription->fresh()->latestInvoice;
    expect($invoice->sub_total)->toEqual(40000);
    expect($invoice->line_items)->toHaveCount(1);
});

it('setup fee is not charged on second subscription', function () {
    $user = User::factory()->create();

    // First subscription
    $plan1 = Plan::factory()->create(['price' => 10000, 'trial_days' => 0, 'default_interval' => 'month', 'interval' => 'month', 'interval_count' => 1]);
    $user->newSubscription('default', $plan1)->saveAndInvoice();

    // Second subscription
    $plan2 = Plan::factory()->create(['price' => 20000, 'trial_days' => 0, 'default_interval' => 'month', 'interval' => 'month', 'interval_count' => 1]);
    $subscription2 = $user->newSubscription('premium', $plan2);
    $subscription2->saveAndInvoice();

    $invoice2 = $subscription2->fresh()->latestInvoice;
    expect($invoice2->sub_total)->toEqual(20000);
    expect($invoice2->line_items)->toHaveCount(1);
});

it('setup fee is not charged on plan swap', function () {
    $user = User::factory()->create();
    $plan1 = Plan::factory()->create(['price' => 40000, 'trial_days' => 0, 'default_interval' => 'month', 'interval' => 'month', 'interval_count' => 1]);
    $subscription = $user->newSubscription('default', $plan1);
    $subscription->saveAndInvoice();

    // Verify first invoice has setup fee
    expect($subscription->fresh()->latestInvoice->sub_total)->toEqual(55000);

    // Swap to another plan
    $plan2 = Plan::factory()->create(['price' => 60000, 'trial_days' => 0, 'default_interval' => 'month', 'interval' => 'month', 'interval_count' => 1]);
    $subscription->swap($plan2->id);

    // Verify swap invoice DOES NOT have setup fee
    $swapInvoice = $subscription->invoices()->orderBy('id', 'desc')->first();
    expect($swapInvoice->sub_total)->toEqual(60000);
    expect($swapInvoice->line_items)->toHaveCount(1);
});

it('setup fee is not charged on renewal', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => 40000, 'trial_days' => 0, 'default_interval' => 'month', 'interval' => 'month', 'interval_count' => 1]);
    $subscription = $user->newSubscription('default', $plan);
    $subscription->saveAndInvoice();

    // Renew subscription
    $subscription->renew();

    $renewalInvoice = $subscription->invoices()->orderBy('id', 'desc')->first();
    expect($renewalInvoice->sub_total)->toEqual(40000);
    expect($renewalInvoice->line_items)->toHaveCount(1);
});
