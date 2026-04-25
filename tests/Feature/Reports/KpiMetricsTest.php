<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Services\Metrics\MetricsService;
use Foundry\Tests\TestCase;

class KpiMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-13 12:00:00'));
    }

    public function test_new_signups_metrics()
    {
        // One user is already seeded by TestCase (DatabaseSeeder)

        // Current month signups: 1 user, 1 sub
        $user1 = User::where('email', 'hello@foundry.com')->first();
        $user1->forceFill(['created_at' => now()->subDays(5)])->save();
        Subscription::factory()->create(['user_id' => $user1->id, 'created_at' => now()->subDays(5)]);

        // Previous month signups: 1 user, 1 sub
        $user2 = User::factory()->create();
        $user2->forceFill(['created_at' => now()->subDays(35)])->save();
        Subscription::factory()->create(['user_id' => $user2->id, 'created_at' => now()->subDays(35)]);

        $metrics = new MetricsService(['start_date' => now()->subMonth()->toDateTimeString()]);
        $result = $metrics->only(['new_customers', 'new_subscriptions']);

        $this->assertEquals(1, $result['new_customers']['current']);
        $this->assertEquals(1, $result['new_customers']['previous']);
        $this->assertEquals(1, $result['new_subscriptions']['current']);
        $this->assertEquals(1, $result['new_subscriptions']['previous']);
    }

    public function test_revenue_and_mrr_metrics()
    {
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
        $this->assertEquals(190, $results['mrr']['raw_current']);
    }

    public function test_net_revenue_and_refund_metrics()
    {
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
        $this->assertEquals(50, $results['net_revenue']['raw_current']);
    }

    public function test_churn_metrics()
    {
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
            'canceled_at' => now()->subDays(15),
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
        $this->assertEquals(0.0909, $results['churn']['raw_current']);

        // Revenue Churn: 100 lost MRR.
        $this->assertEquals(100, $results['revenue_churn']['lost_mrr']);
        $this->assertEquals(1.0, $results['revenue_churn']['raw_current']);
    }

    public function test_order_count_metric()
    {
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

        $this->assertEquals(3, $results['order_count']['raw_current']);
    }

    public function test_active_users_metrics()
    {
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

        $this->assertEquals(2, $results['active_users']['raw_current']);
    }

    public function test_ltv_and_arpu_metrics()
    {
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
            'canceled_at' => now()->subDays(5),
            'expires_at' => now()->addMonth(),
        ]);

        $metrics = new MetricsService([]);
        $results = $metrics->only(['arpu', 'churn', 'ltv']);

        // ARPU = 100 / 22 = 4.55
        $this->assertEquals(4.55, $results['arpu']['raw_current']);
        $this->assertEquals(0.0455, $results['churn']['raw_current']);

        // LTV = 4.55 / 0.0455 = 100
        $this->assertEquals(100, $results['ltv']['raw_current']);
    }

    public function test_grace_period_and_trial_active_users()
    {
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
        $this->assertEquals(4, $results['active_users']['raw_current']);
    }
}
