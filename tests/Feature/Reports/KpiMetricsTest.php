<?php

use Carbon\Carbon;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Metrics\MetricsService;
use Foundry\Tests\TestCase;
use Workbench\App\Models\User;

uses(TestCase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-04-13 12:00:00'));
});

it('new signups metrics', function () {
    // One user is already seeded by TestCase (DatabaseSeeder)

    // Current month signups: 1 user, 1 sub
    $user1 = User::where('email', 'user@example.com')->first();
    $user1->forceFill(['created_at' => now()->subDays(5)])->save();
    Subscription::factory()->create(['user_id' => $user1->id, 'created_at' => now()->subDays(5)]);

    // Previous month signups: 1 user, 1 sub
    $user2 = User::factory()->create();
    $user2->forceFill(['created_at' => now()->subDays(35)])->save();
    Subscription::factory()->create(['user_id' => $user2->id, 'created_at' => now()->subDays(35)]);

    $metrics = new MetricsService(['start_date' => now()->subMonth()->toDateTimeString()]);
    $result = $metrics->only(['new_customers', 'new_subscriptions']);

    expect($result['new_customers']['current'])->toEqual(1);
    expect($result['new_customers']['previous'])->toEqual(1);
    expect($result['new_subscriptions']['current'])->toEqual(1);
    expect($result['new_subscriptions']['previous'])->toEqual(1);
});

it('revenue and mrr metrics', function () {
    $plan = Plan::factory()->create(['price' => 100, 'interval' => 'month']);

    // Subscription 1: Paid $80 (after $20 discount), $10 tax. grand_total = 90.
    $sub1 = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
    ]);
    Order::factory()->create([
        'orderable_id' => $sub1->id,
        'orderable_type' => (new Foundry::$subscriptionModel)->getMorphClass(),
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 90,
        'tax_total' => 10,
        'created_at' => now()->subDays(10),
    ]);

    // Subscription 2: Annual. Paid $1320 (after discount), $120 tax. grand_total = 1440.
    // MRR should be (1320 / 12) = 110.
    $sub2 = Subscription::factory()->create([
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_interval' => 'year',
        'billing_interval_count' => 1,
    ]);
    Order::factory()->create([
        'orderable_id' => $sub2->id,
        'orderable_type' => (new Foundry::$subscriptionModel)->getMorphClass(),
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 1440,
        'tax_total' => 120,
        'created_at' => now()->subDays(5),
    ]);

    $metrics = new MetricsService([]);
    $results = $metrics->only(['mrr']);

    // Assert MRR: 80 (sub1) + 110 (sub2) = 190
    expect($results['mrr']['raw_current'])->toEqual(190);
});

it('net revenue and refund metrics', function () {
    // Order 1: Paid $100 net ($110 grand, $10 tax)
    Order::factory()->create([
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 110,
        'tax_total' => 10,
        'created_at' => now()->subDays(2),
    ]);

    // Order 2: Refunded $50
    Order::factory()->create([
        'payment_status' => Order::STATUS_REFUNDED,
        'grand_total' => 60,
        'tax_total' => 10,
        'refund_total' => 50,
        'created_at' => now()->subDays(1),
    ]);

    $metrics = new MetricsService([]);
    $results = $metrics->only(['net_revenue']);

    // Revenue (PAID only): 100. Refunds = 50. Net = 50.
    expect($results['net_revenue']['raw_current'])->toEqual(50);
});

it('churn metrics', function () {
    $plan = Plan::factory()->create(['price' => 100, 'interval' => 'month']);

    // 10 active at start
    Subscription::factory()->count(10)->create([
        'status' => 'active',
        'created_at' => now()->subMonth()->subDays(2),
        'starts_at' => now()->subMonth()->subDays(2),
    ]);

    // 1 cancels during period. It was $100/mo.
    $uC = User::factory()->create();
    $sub = Subscription::factory()->create([
        'user_id' => $uC->id,
        'plan_id' => $plan->id,
        'status' => 'canceled',
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'created_at' => now()->subMonth()->subDays(2),
        'starts_at' => now()->subMonth()->subDays(2),
        'expires_at' => now()->addMonth(),
        'cancels_at' => now()->subDays(15),
    ]);
    Order::factory()->create([
        'orderable_id' => $sub->id,
        'orderable_type' => (new Foundry::$subscriptionModel)->getMorphClass(),
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 100,
        'tax_total' => 0,
        'created_at' => now()->subMonth()->subDays(1),
    ]);

    $metrics = new MetricsService([]);

    // Debug: check mrr at start
    $mrrStart = $metrics->only(['mrr']); // This returns mrr at NOW and mrr PREVIOUS (which is at start)
    // Wait, current is now, previous is 1 month ago.

    $results = $metrics->only(['churn', 'revenue_churn']);

    // Churn Rate: 1 / 11 = 0.0909
    expect($results['churn']['raw_current'])->toEqual(0.0909);

    // Revenue Churn: 100 lost MRR.
    expect($results['revenue_churn']['lost_mrr'])->toEqual(100);
    expect($results['revenue_churn']['raw_current'])->toEqual(1.0);
});

it('order count metric', function () {
    $user = User::factory()->create();

    // Paid twice
    Order::factory()->create(['customer_id' => $user->id, 'payment_status' => Order::STATUS_PAID, 'created_at' => now()->subDays(10)]);
    Order::factory()->create(['customer_id' => $user->id, 'payment_status' => Order::STATUS_PAID, 'created_at' => now()->subDays(5)]);

    // 1 failed payment
    Order::factory()->create([
        'payment_status' => Order::STATUS_PAYMENT_FAILED,
        'created_at' => now()->subDays(1),
    ]);

    $metrics = new MetricsService([]);
    $results = $metrics->only(['order_count']);

    expect($results['order_count']['raw_current'])->toEqual(3);
});

it('active users metrics', function () {
    // One active subscriber
    $u1 = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $u1->id,
        'status' => 'active',
        'created_at' => now()->subDays(10),
    ]);

    // One active orderer (no sub)
    $u2 = User::factory()->create();
    Order::factory()->create([
        'customer_id' => $u2->id,
        'payment_status' => Order::STATUS_PAID,
        'created_at' => now()->subDays(2),
    ]);

    $metrics = new MetricsService([]);
    $results = $metrics->only(['active_users']);

    expect($results['active_users']['raw_current'])->toEqual(2);
});

it('ltv and arpu metrics', function () {
    // Subscription 1: $100/mo
    $user1 = User::factory()->create(['created_at' => now()->subMonths(2)]);
    $sub = Subscription::factory()->create([
        'user_id' => $user1->id,
        'status' => 'active',
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'created_at' => now()->subMonths(2),
        'starts_at' => now()->subMonths(2),
    ]);
    Order::factory()->create([
        'orderable_id' => $sub->id,
        'orderable_type' => (new Foundry::$subscriptionModel)->getMorphClass(),
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 100,
        'tax_total' => 0,
        'created_at' => now()->subDays(15),
    ]);

    // Create 20 more active users/subs
    for ($i = 0; $i < 20; $i++) {
        $u = User::factory()->create(['created_at' => now()->subMonths(2)]);
        Subscription::factory()->create([
            'user_id' => $u->id,
            'status' => 'active',
            'created_at' => now()->subMonths(2),
            'starts_at' => now()->subMonths(2),
        ]);
    }

    // Create 1 more user via canceling subscription
    $uC1 = User::factory()->create(['created_at' => now()->subMonths(2)]);
    Subscription::factory()->create([
        'user_id' => $uC1->id,
        'status' => 'active',
        'created_at' => now()->subMonths(2),
        'starts_at' => now()->subMonths(2),
        'cancels_at' => now()->subDays(5),
        'expires_at' => now()->addMonth(),
    ]);

    $metrics = new MetricsService([]);
    $results = $metrics->only(['arpu', 'churn', 'ltv']);

    // ARPU = 100 / 22 = 4.55
    expect($results['arpu']['raw_current'])->toEqual(4.55);
    expect($results['churn']['raw_current'])->toEqual(0.0455);

    // LTV = 4.55 / 0.0455 = 100
    expect($results['ltv']['raw_current'])->toEqual(100);
});

it('grace period and trial active users', function () {
    // 1. Paid active user
    $u1 = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $u1->id,
        'status' => 'active',
        'expires_at' => now()->addMonth(),
    ]);

    // 2. User in grace period (payment failed, ends_at in future)
    $u2 = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $u2->id,
        'status' => 'active',
        'expires_at' => now()->subDay(), // Past renewal but...
        'ends_at' => now()->addDays(5),  // ...still has grace access
    ]);

    // 3. User past grace period (should NOT be counted even if status is active)
    $u3 = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $u3->id,
        'status' => 'active',
        'expires_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
    ]);

    // 4. User in trial
    $u4 = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $u4->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->addDays(10),
    ]);

    // 5. Free forever user
    $u5 = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $u5->id,
        'is_free_forever' => true,
        'status' => 'active',
    ]);

    $metrics = new MetricsService([]);
    $results = $metrics->only(['active_users']);

    // Total should be 4 (u1, u2, u4, u5). u3 is EXCLUDED.
    expect($results['active_users']['raw_current'])->toEqual(4);
});
