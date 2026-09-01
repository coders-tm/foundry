<?php

uses(\Foundry\Tests\TestCase::class);

beforeEach(function () {
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-04-13 12:00:00'));
});

it('new signups metrics', function () {
    // One user is already seeded by TestCase (DatabaseSeeder)
    
    // Current month signups: 1 user, 1 sub
    $user1 = \Workbench\App\Models\User::where('email', 'user@example.com')->first();
    $user1->forceFill(['created_at' => now()->subDays(5)])->save();
    \Foundry\Models\Subscription::factory()->create(['user_id' => $user1->id, 'created_at' => now()->subDays(5)]);
    
    // Previous month signups: 1 user, 1 sub
    $user2 = \Workbench\App\Models\User::factory()->create();
    $user2->forceFill(['created_at' => now()->subDays(35)])->save();
    \Foundry\Models\Subscription::factory()->create(['user_id' => $user2->id, 'created_at' => now()->subDays(35)]);
    
    $metrics = new \Foundry\Services\Metrics\MetricsService(['start_date' => now()->subMonth()->toDateTimeString()]);
    $result = $metrics->only(['new_customers', 'new_subscriptions']);
    
    $this->assertEquals(1, $result['new_customers']['current']);
    $this->assertEquals(1, $result['new_customers']['previous']);
    $this->assertEquals(1, $result['new_subscriptions']['current']);
    $this->assertEquals(1, $result['new_subscriptions']['previous']);
});

it('revenue and mrr metrics', function () {
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 100, 'interval' => 'month']);
    
    // Subscription 1: Paid $80 (after $20 discount), $10 tax. grand_total = 90.
    $sub1 = \Foundry\Models\Subscription::factory()->create([
    'plan_id' => $plan->id,
    'status' => 'active',
    'billing_interval' => 'month',
    'billing_interval_count' => 1,
    ]);
    \Foundry\Models\Order::factory()->create([
    'orderable_id' => $sub1->id,
    'orderable_type' => (new Foundry::$subscriptionModel)->getMorphClass(),
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 90,
    'tax_total' => 10,
    'created_at' => now()->subDays(10),
    ]);
    
    // Subscription 2: Annual. Paid $1320 (after discount), $120 tax. grand_total = 1440.
    // MRR should be (1320 / 12) = 110.
    $sub2 = \Foundry\Models\Subscription::factory()->create([
    'plan_id' => $plan->id,
    'status' => 'active',
    'billing_interval' => 'year',
    'billing_interval_count' => 1,
    ]);
    \Foundry\Models\Order::factory()->create([
    'orderable_id' => $sub2->id,
    'orderable_type' => (new Foundry::$subscriptionModel)->getMorphClass(),
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 1440,
    'tax_total' => 120,
    'created_at' => now()->subDays(5),
    ]);
    
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $results = $metrics->only(['mrr']);
    
    // Assert MRR: 80 (sub1) + 110 (sub2) = 190
    $this->assertEquals(190, $results['mrr']['raw_current']);
});

it('net revenue and refund metrics', function () {
    // Order 1: Paid $100 net ($110 grand, $10 tax)
    \Foundry\Models\Order::factory()->create([
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 110,
    'tax_total' => 10,
    'created_at' => now()->subDays(2),
    ]);
    
    // Order 2: Refunded $50
    \Foundry\Models\Order::factory()->create([
    'payment_status' => \Foundry\Models\Order::STATUS_REFUNDED,
    'grand_total' => 60,
    'tax_total' => 10,
    'refund_total' => 50,
    'created_at' => now()->subDays(1),
    ]);
    
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $results = $metrics->only(['net_revenue']);
    
    // Revenue (PAID only): 100. Refunds = 50. Net = 50.
    $this->assertEquals(50, $results['net_revenue']['raw_current']);
});

it('churn metrics', function () {
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 100, 'interval' => 'month']);
    
    // 10 active at start
    \Foundry\Models\Subscription::factory()->count(10)->create([
    'status' => 'active',
    'created_at' => now()->subMonth()->subDays(2),
    'starts_at' => now()->subMonth()->subDays(2),
    ]);
    
    // 1 cancels during period. It was $100/mo.
    $uC = \Workbench\App\Models\User::factory()->create();
    $sub = \Foundry\Models\Subscription::factory()->create([
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
    \Foundry\Models\Order::factory()->create([
    'orderable_id' => $sub->id,
    'orderable_type' => (new Foundry::$subscriptionModel)->getMorphClass(),
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 100,
    'tax_total' => 0,
    'created_at' => now()->subMonth()->subDays(1),
    ]);
    
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    
    // Debug: check mrr at start
    $mrrStart = $metrics->only(['mrr']); // This returns mrr at NOW and mrr PREVIOUS (which is at start)
    // Wait, current is now, previous is 1 month ago.
    
    $results = $metrics->only(['churn', 'revenue_churn']);
    
    // Churn Rate: 1 / 11 = 0.0909
    $this->assertEquals(0.0909, $results['churn']['raw_current']);
    
    // Revenue Churn: 100 lost MRR.
    $this->assertEquals(100, $results['revenue_churn']['lost_mrr']);
    $this->assertEquals(1.0, $results['revenue_churn']['raw_current']);
});

it('order count metric', function () {
    $user = \Workbench\App\Models\User::factory()->create();
    
    // Paid twice
    \Foundry\Models\Order::factory()->create(['customer_id' => $user->id, 'payment_status' => \Foundry\Models\Order::STATUS_PAID, 'created_at' => now()->subDays(10)]);
    \Foundry\Models\Order::factory()->create(['customer_id' => $user->id, 'payment_status' => \Foundry\Models\Order::STATUS_PAID, 'created_at' => now()->subDays(5)]);
    
    // 1 failed payment
    \Foundry\Models\Order::factory()->create([
    'payment_status' => \Foundry\Models\Order::STATUS_PAYMENT_FAILED,
    'created_at' => now()->subDays(1),
    ]);
    
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $results = $metrics->only(['order_count']);
    
    $this->assertEquals(3, $results['order_count']['raw_current']);
});

it('active users metrics', function () {
    // One active subscriber
    $u1 = \Workbench\App\Models\User::factory()->create();
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => $u1->id,
    'status' => 'active',
    'created_at' => now()->subDays(10),
    ]);
    
    // One active orderer (no sub)
    $u2 = \Workbench\App\Models\User::factory()->create();
    \Foundry\Models\Order::factory()->create([
    'customer_id' => $u2->id,
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'created_at' => now()->subDays(2),
    ]);
    
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $results = $metrics->only(['active_users']);
    
    $this->assertEquals(2, $results['active_users']['raw_current']);
});

it('ltv and arpu metrics', function () {
    // Subscription 1: $100/mo
    $user1 = \Workbench\App\Models\User::factory()->create(['created_at' => now()->subMonths(2)]);
    $sub = \Foundry\Models\Subscription::factory()->create([
    'user_id' => $user1->id,
    'status' => 'active',
    'billing_interval' => 'month',
    'billing_interval_count' => 1,
    'created_at' => now()->subMonths(2),
    'starts_at' => now()->subMonths(2),
    ]);
    \Foundry\Models\Order::factory()->create([
    'orderable_id' => $sub->id,
    'orderable_type' => (new Foundry::$subscriptionModel)->getMorphClass(),
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 100,
    'tax_total' => 0,
    'created_at' => now()->subDays(15),
    ]);
    
    // Create 20 more active users/subs
    for ($i = 0; $i < 20; $i++) {
    $u = \Workbench\App\Models\User::factory()->create(['created_at' => now()->subMonths(2)]);
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => $u->id,
    'status' => 'active',
    'created_at' => now()->subMonths(2),
    'starts_at' => now()->subMonths(2),
    ]);
    }
    
    // Create 1 more user via canceling subscription
    $uC1 = \Workbench\App\Models\User::factory()->create(['created_at' => now()->subMonths(2)]);
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => $uC1->id,
    'status' => 'active',
    'created_at' => now()->subMonths(2),
    'starts_at' => now()->subMonths(2),
    'cancels_at' => now()->subDays(5),
    'expires_at' => now()->addMonth(),
    ]);
    
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $results = $metrics->only(['arpu', 'churn', 'ltv']);
    
    // ARPU = 100 / 22 = 4.55
    $this->assertEquals(4.55, $results['arpu']['raw_current']);
    $this->assertEquals(0.0455, $results['churn']['raw_current']);
    
    // LTV = 4.55 / 0.0455 = 100
    $this->assertEquals(100, $results['ltv']['raw_current']);
});

it('grace period and trial active users', function () {
    // 1. Paid active user
    $u1 = \Workbench\App\Models\User::factory()->create();
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => $u1->id,
    'status' => 'active',
    'expires_at' => now()->addMonth(),
    ]);
    
    // 2. User in grace period (payment failed, ends_at in future)
    $u2 = \Workbench\App\Models\User::factory()->create();
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => $u2->id,
    'status' => 'active',
    'expires_at' => now()->subDay(), // Past renewal but...
    'ends_at' => now()->addDays(5),  // ...still has grace access
    ]);
    
    // 3. User past grace period (should NOT be counted even if status is active)
    $u3 = \Workbench\App\Models\User::factory()->create();
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => $u3->id,
    'status' => 'active',
    'expires_at' => now()->subDays(10),
    'ends_at' => now()->subDay(),
    ]);
    
    // 4. User in trial
    $u4 = \Workbench\App\Models\User::factory()->create();
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => $u4->id,
    'status' => 'trialing',
    'trial_ends_at' => now()->addDays(10),
    ]);
    
    // 5. Free forever user
    $u5 = \Workbench\App\Models\User::factory()->create();
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => $u5->id,
    'is_free_forever' => true,
    'status' => 'active',
    ]);
    
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $results = $metrics->only(['active_users']);
    
    // Total should be 4 (u1, u2, u4, u5). u3 is EXCLUDED.
    $this->assertEquals(4, $results['active_users']['raw_current']);
});
