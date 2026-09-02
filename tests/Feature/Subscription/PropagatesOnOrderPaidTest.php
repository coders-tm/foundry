<?php

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['app.currency' => 'USD']);
});

$createPlan = function (string $interval = 'month', int $count = 1) {
    $plan = new Plan([
        'label' => 'Test Plan', 'slug' => 'test-plan', 'description' => 'A test plan', 'is_active' => true,
        'default_interval' => $interval, 'interval' => $interval, 'interval_count' => $count,
        'price' => 1000, 'trial_days' => 0, 'options' => null,
    ]);
    $plan->save();

    return $plan;
};

$createSubscriptionWithOpenInvoice = function (bool $pastDue = true) use ($createPlan) {
    $plan = $createPlan('month', 1);
    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'status' => $pastDue ? Subscription::EXPIRED : Subscription::PENDING,
        'starts_at' => now(), 'expires_at' => now()->addMonth(),
    ]);
    $start = Carbon::now()->subMonth()->startOfDay();
    $end = (clone $start)->addMonth();
    $description = $start->format('M d, Y').' - '.$end->format('M d, Y');
    $order = Order::create([
        'customer_id' => $subscription->user_id, 'orderable_id' => $subscription->id, 'orderable_type' => Subscription::class,
        'collect_tax' => false, 'source' => 'Membership', 'sub_total' => 1000, 'tax_total' => 0, 'discount_total' => 0, 'grand_total' => 1000, 'due_date' => $end,
    ]);
    $order->line_items()->create(['title' => 'Plan line', 'description' => $description, 'price' => 1000, 'total' => 1000, 'quantity' => 1, 'options' => ['title' => 'Plan']]);

    return [$subscription->fresh(), $order->fresh()];
};

it('mark as paid activates subscription and sets period based on subscription when paid', function () use ($createSubscriptionWithOpenInvoice) {
    config()->set('foundry.subscription.anchor_from_invoice', true);
    [$subscription, $order] = $createSubscriptionWithOpenInvoice(true);
    $order->markAsPaid(1, ['note' => 'manual']);
    $subscription = $subscription->fresh();
    expect($subscription->status)->toEqual(Subscription::ACTIVE);
    expect($subscription->starts_at)->not->toBeNull();
    expect($subscription->starts_at->isSameDay(now()))->toBeTrue();
});

it('mark as paid activates subscription and sets period from today when anchoring disabled', function () use ($createSubscriptionWithOpenInvoice) {
    config()->set('foundry.subscription.anchor_from_invoice', false);
    [$subscription, $order] = $createSubscriptionWithOpenInvoice(true);
    $order->markAsPaid(1, ['note' => 'manual']);
    $subscription = $subscription->fresh();
    expect($subscription->status)->toEqual(Subscription::ACTIVE);
    expect($subscription->starts_at->isSameDay(now()))->toBeTrue();
});
