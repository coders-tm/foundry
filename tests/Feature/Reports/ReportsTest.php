<?php

use App\Models\User;
use Foundry\Models\Admin;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
    $this->actingAs($this->admin, 'admin');
});

$seedTestData = function () {
    for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
        $count = rand(2, 5);
        User::factory()->count($count)->create(['created_at' => now()->subMonths($monthsAgo)->addDays(rand(0, 28))]);
    }
    $monthlyPlan = Plan::factory()->create(['price' => 29.99, 'interval' => 'month', 'interval_count' => 1]);
    $yearlyPlan = Plan::factory()->create(['price' => 299.99, 'interval' => 'year', 'interval_count' => 1]);
    $users = User::all();
    for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
        $subsCount = rand(3, 6);
        for ($i = 0; $i < $subsCount; $i++) {
            $plan = rand(0, 1) ? $monthlyPlan : $yearlyPlan;
            $user = $users->random();
            $createdAt = now()->subMonths($monthsAgo)->addDays(rand(0, 28));
            $rand = rand(1, 100);
            if ($rand <= 70) {
                Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'created_at' => $createdAt]);
            } elseif ($rand <= 85) {
                Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'trial_ends_at' => now()->addDays(rand(1, 14)), 'created_at' => $createdAt]);
            } else {
                Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'canceled', 'cancels_at' => $createdAt->copy()->addMonths(rand(1, 3)), 'created_at' => $createdAt]);
            }
        }
    }
    for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
        $ordersCount = rand(5, 10);
        for ($i = 0; $i < $ordersCount; $i++) {
            Order::factory()->create(['customer_id' => $users->random()->id, 'payment_status' => rand(1, 100) <= 95 ? 'paid' : 'pending', 'grand_total' => rand(50, 500) + (rand(0, 99) / 100), 'created_at' => now()->subMonths($monthsAgo)->addDays(rand(0, 28))]);
        }
    }
};

it('subscription metrics endpoint works', function () {
    $response = $this->getJson('/admin/reports/metrics?category=retention');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'active_count',
            'trial_count',
            'grace_period_count',
            'cancelled_count',
            'churn_rate',
            'trial_conversion_rate',
        ]);
});

it('order metrics endpoint works', function () {
    $response = $this->getJson('/admin/reports/metrics?category=revenue');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'total_revenue',
            'mrr',
            'arr',
            'aov',
        ]);
});

it('customer metrics endpoint works', function () {
    $response = $this->getJson('/admin/reports/metrics?category=customers');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'total_count',
            'new_customers',
            'growth_rate',
            'clv',
            'segments',
        ]);
});

it('revenue chart endpoint works', function () use ($seedTestData) {
    $seedTestData();
    $response = $this->getJson('/admin/reports/charts?type=revenue&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify we have 12 months of data
    $this->assertCount(12, $data);

    // Verify at least some months have revenue > 0
    $totalRevenue = array_sum($data);
    $this->assertGreaterThan(0, $totalRevenue, 'Total revenue should be greater than 0');
});

it('subscription chart endpoint works', function () use ($seedTestData) {
    $seedTestData();
    $response = $this->getJson('/admin/reports/charts?type=subscriptions&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify we have 12 months of data
    $this->assertCount(12, $data);

    // Verify data structure
    foreach ($data as $monthLabel => $monthData) {
        $this->assertArrayHasKey('new', $monthData);
        $this->assertArrayHasKey('cancelled', $monthData);
        $this->assertArrayHasKey('net', $monthData);
    }

    // Verify at least some months have new subscriptions
    $totalNew = array_sum(array_column($data, 'new'));
    $this->assertGreaterThan(0, $totalNew, 'Should have new subscriptions');
});

it('customer chart endpoint works', function () {
    $response = $this->getJson('/admin/reports/charts?type=customers&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify we have 12 months of data
    $this->assertCount(12, $data);

    // Verify at least some months have new customers
    $totalCustomers = array_sum($data);
    $this->assertGreaterThan(0, $totalCustomers, 'Should have new customers');
});

it('order chart endpoint works', function () use ($seedTestData) {
    $seedTestData();
    $response = $this->getJson('/admin/reports/charts?type=orders&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify we have 12 months of data
    $this->assertCount(12, $data);

    // Verify data structure
    foreach ($data as $monthLabel => $monthData) {
        $this->assertArrayHasKey('orders', $monthData);
        $this->assertArrayHasKey('revenue', $monthData);
    }

    // Verify at least some months have orders
    $totalOrders = array_sum(array_column($data, 'orders'));
    $this->assertGreaterThan(0, $totalOrders, 'Should have orders');
});

it('mrr chart endpoint works', function () {
    $response = $this->getJson('/admin/reports/charts?type=mrr&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify we have 12 months of data
    $this->assertCount(12, $data);

    // MRR should be numeric values
    foreach ($data as $monthLabel => $mrr) {
        $this->assertIsNumeric($mrr);
    }
});

it('churn chart endpoint works', function () {
    $response = $this->getJson('/admin/reports/charts?type=churn&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify we have 12 months of data
    $this->assertCount(12, $data);

    // Verify data structure
    foreach ($data as $monthLabel => $monthData) {
        $this->assertArrayHasKey('churned', $monthData);
        $this->assertArrayHasKey('rate', $monthData);
    }
});

it('revenue breakdown chart endpoint works', function () {
    $response = $this->getJson('/admin/reports/charts?type=revenue-breakdown&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify data structure (pie chart format)
    $this->assertIsArray($data);

    // Should have subscription and product revenue
    $totalRevenue = array_sum($data);
    $this->assertGreaterThanOrEqual(0, $totalRevenue);
});

it('members breakdown chart endpoint works', function () use ($seedTestData) {
    $seedTestData();
    $response = $this->getJson('/admin/reports/charts?type=members-breakdown&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify data structure (pie chart format)
    $this->assertIsArray($data);

    // Should have Active, On Trial, Grace Period, Cancelled segments
    $this->assertArrayHasKey('Active', $data);
    $this->assertArrayHasKey('On Trial', $data);
    $this->assertArrayHasKey('Cancelled', $data);

    $totalMembers = array_sum($data);
    $this->assertGreaterThan(0, $totalMembers, 'Should have members in breakdown');
});

it('arpu chart endpoint works', function () {
    $response = $this->getJson('/admin/reports/charts?type=arpu&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify we have 12 months of data
    $this->assertCount(12, $data);

    // ARPU should be numeric values
    foreach ($data as $monthLabel => $arpu) {
        $this->assertIsNumeric($arpu);
    }
});

it('plan distribution chart endpoint works', function () {
    $response = $this->getJson('/admin/reports/charts?type=plan-distribution&period=month');

    $response->assertStatus(200);

    $data = $response->json();

    // Verify data structure (pie chart format)
    $this->assertIsArray($data);

    // If we have active subscriptions, should have plan entries
    if (count($data) > 0) {
        foreach ($data as $planLabel => $count) {
            $this->assertIsString($planLabel);
            $this->assertIsInt($count);
            $this->assertGreaterThan(0, $count);
        }
    }
});

it('cache clear endpoint works', function () {
    $response = $this->postJson('/admin/reports/clear-cache');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Reports cache cleared successfully',
        ]);
});

it('date filtering works', function () {
    $response = $this->getJson('/admin/reports/metrics?category=retention&start_date=2025-01-01&end_date=2025-11-24');

    $response->assertStatus(200);
});

it('compare flag returns comparisons', function () {
    $response = $this->getJson('/admin/reports/metrics?category=retention&compare=1');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'cancelled_count' => [
                'current',
                'previous',
                'trend',
            ],
            'new_subscriptions' => [
                'current',
                'previous',
                'trend',
            ],
            'churn_rate' => [
                'current',
                'previous',
                'trend',
            ],
            'trial_conversion_rate' => [
                'current',
                'previous',
                'trend',
            ],
        ]);
});

it('chart type validation', function () {
    $response = $this->getJson('/admin/reports/charts?type=invalid&period=month');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

it('new chart type validation', function () {
    // Verify the new chart types are accepted
    $response = $this->getJson('/admin/reports/charts?type=arpu&period=month');
    $response->assertStatus(200);

    $response = $this->getJson('/admin/reports/charts?type=plan-distribution&period=month');
    $response->assertStatus(200);
});
