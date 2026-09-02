<?php

use Foundry\Foundry;
use Foundry\Models\Tax;
use Foundry\Tests\Feature\FeatureTestCase;

uses(FeatureTestCase::class);

beforeEach(function () {

    // Remove any existing tax rates to avoid conflicts
    Tax::truncate();

    // Seed a 10% tax rate for United States
    Tax::create([
        'label' => 'GST',
        'code' => 'US',
        'rate' => 10,
        'state' => '*',
        'priority' => 1,
        'compounded' => false,
    ]);

    // Fallback for any other country if needed
    Tax::create([
        'label' => 'Tax',
        'code' => '*',
        'rate' => 10,
        'state' => '*',
        'priority' => 1,
        'compounded' => false,
    ]);
});

it('upcoming invoice calculations', function () {
    // 1. Setup: Create a plan with price $100
    $plan = Foundry::$planModel::factory()->create([
        'price' => 100.00,
        'label' => 'Monthly Plan',
        'interval' => 'month',
        'interval_count' => 1,
    ]);

    // 2. Setup: Create a coupon with 20% discount
    $coupon = Foundry::$couponModel::factory()->create([
        'name' => 'Save 20',
        'value' => 20,
        'discount_type' => 'percentage',
        'promotion_code' => 'SAVE20',
    ]);

    // 3. Setup: Create a user and subscription with the coupon
    $user = Foundry::$userModel::factory()->withAddress(['country' => 'United States'])->create();

    $subscription = Foundry::$subscriptionModel::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'coupon_id' => $coupon->id,
    ]);

    $subscription->refresh();

    // 4. Action: Get upcoming invoice
    $upcomingInvoice = $subscription->upcomingInvoice();

    // 5. Verification:
    // Expected: Subtotal 100, Discount 20, Grand Total 80 + Tax (8) = 88
    expect($upcomingInvoice)->not->toBeNull();

    // Asserting the values that the user EXPECTS (which are currently different)
    expect($upcomingInvoice->sub_total)->toEqual(100.00);
    expect($upcomingInvoice->tax_total)->toEqual(8.00);
    expect($upcomingInvoice->discount_total)->toEqual(20.00);
    expect($upcomingInvoice->grand_total)->toEqual(88.00);
});

it('generated invoice calculations', function () {
    // 1. Setup: Create a plan with price $100
    $plan = Foundry::$planModel::factory()->create([
        'price' => 100.00,
        'label' => 'Monthly Plan',
        'interval' => 'month',
        'interval_count' => 1,
    ]);

    // 2. Setup: Create a coupon with $15 fixed discount
    $coupon = Foundry::$couponModel::factory()->create([
        'name' => 'Save 15',
        'value' => 15,
        'discount_type' => 'fixed',
        'promotion_code' => 'SAVE15',
    ]);

    // 3. Setup: Create a user and subscription
    $user = Foundry::$userModel::factory()->withAddress(['country' => 'United States'])->create();

    /** @var Subscription $subscription */
    $subscription = Foundry::$subscriptionModel::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'coupon_id' => $coupon->id,
    ]);

    $subscription->refresh();

    // 4. Action: Generate invoice
    $invoice = $subscription->generateInvoice();

    foreach ($invoice->line_items as $item) {
        expect($item->quantity)->toEqual(1);
        expect($item->taxable)->toBeTrue();
    }

    // 5. Verification:
    expect($invoice)->not->toBeNull();
    expect($invoice->sub_total)->toEqual(100.00);
    expect($invoice->tax_total)->toEqual(8.5);
    expect($invoice->discount_total)->toEqual(15.00);
    expect($invoice->grand_total)->toEqual(93.50);
});
