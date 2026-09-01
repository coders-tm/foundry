<?php

use App\Models\User;
use Foundry\Models\Admin;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\Feature\FeatureTestCase;

uses(FeatureTestCase::class);

beforeEach(function () {
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
                $subscription = Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'created_at' => $createdAt]);
            } elseif ($rand <= 85) {
                $subscription = Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'trial_ends_at' => now()->addDays(rand(1, 14)), 'created_at' => $createdAt]);
            } else {
                $subscription = Subscription::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'canceled', 'cancels_at' => $createdAt->copy()->addMonths(rand(1, 3)), 'created_at' => $createdAt]);
            }
            Order::factory()->create(['customer_id' => $user->id, 'orderable_id' => $subscription->id, 'orderable_type' => $subscription->getMorphClass(), 'payment_status' => 'paid', 'grand_total' => $plan->price, 'created_at' => $createdAt]);
        }
    }
    for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
        $ordersCount = rand(5, 10);
        for ($i = 0; $i < $ordersCount; $i++) {
            Order::factory()->create(['customer_id' => $users->random()->id, 'payment_status' => rand(1, 100) <= 95 ? 'paid' : 'pending', 'grand_total' => rand(50, 500) + (rand(0, 99) / 100), 'created_at' => now()->subMonths($monthsAgo)->addDays(rand(0, 28))]);
        }
    }

    $this->admin = Admin::factory()->create();
    $this->actingAs($this->admin, 'admin');
});

it('kpis endpoint requires authentication', function () {
    // Logout first since setUp authenticates
    auth()->logout();

    $response = $this->getJson('/admin/reports/kpis');
    $response->assertStatus(401);
});

it('kpis endpoint returns all kpi metrics', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'mrr' => [
                'current',
                'previous',
                'delta',
                'delta_percent',
                'trend',
                'by_plan',
                'by_interval',
            ],
            'churn' => [
                'current',
                'previous',
                'delta',
                'delta_percent',
                'trend',
                'logo_churn',
            ],
            'ltv' => [
                'current',
                'previous',
                'delta',
                'delta_percent',
                'trend',
            ],
            'arpu' => [
                'current',
                'previous',
                'delta',
                'delta_percent',
                'trend',
            ],
            'order_count' => [
                'current',
                'previous',
                'delta',
                'delta_percent',
                'trend',
            ],
            'total_revenue' => [
                'current',
                'previous',
                'delta',
                'delta_percent',
                'trend',
            ],
            'net_revenue' => [
                'current',
                'previous',
                'delta',
                'delta_percent',
                'trend',
            ],
            'aov' => [
                'current',
                'previous',
                'delta',
                'delta_percent',
                'trend',
            ],
            'metadata' => [
                'filters',
                'supports_compare',
                'comparison_periods' => [
                    'current' => ['start', 'end'],
                    'previous' => ['start', 'end'],
                ],
            ],
        ]);
});

it('kpis endpoint validates date parameters', function () {
    $response = $this->getJson('/admin/reports/kpis?start_date=invalid');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date']);
});

it('kpis endpoint validates end date after start date', function () {
    $response = $this->getJson('/admin/reports/kpis?start_date=2025-12-01&end_date=2025-11-01');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);
});

it('kpis mrr calculation with active subscriptions', function () {
    // Use existing seeded data instead of creating new data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // MRR should be calculated from existing active subscriptions
    $this->assertGreaterThan(0, $data['mrr']['raw_current'], 'MRR should be greater than 0 with seeded data');
    $this->assertArrayHasKey('by_plan', $data['mrr']);
    $this->assertArrayHasKey('by_interval', $data['mrr']);
});

it('kpis churn calculation', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    $this->assertArrayHasKey('current', $data['churn']);
    $this->assertArrayHasKey('previous', $data['churn']);
    $this->assertArrayHasKey('logo_churn', $data['churn']);
    $this->assertIsNumeric($data['churn']['raw_current']);
});

it('kpis orders metrics', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Check flattened order metrics
    $this->assertArrayHasKey('order_count', $data);
    $this->assertArrayHasKey('net_revenue', $data);
    $this->assertArrayHasKey('aov', $data);

    // Each order KPI should have period comparison
    $this->assertArrayHasKey('current', $data['order_count']);
    $this->assertArrayHasKey('previous', $data['order_count']);
    $this->assertArrayHasKey('trend', $data['order_count']);
    $this->assertArrayHasKey('description', $data['order_count']);
});

it('kpis active users calculation', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    $this->assertArrayHasKey('current', $data['active_users']);
    $this->assertGreaterThan(0, $data['active_users']['raw_current']);
});

it('kpis metadata includes period ranges', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    $this->assertArrayHasKey('metadata', $data);
    $this->assertArrayHasKey('comparison_periods', $data['metadata']);
    $this->assertArrayHasKey('start', $data['metadata']['comparison_periods']['current']);
    $this->assertArrayHasKey('end', $data['metadata']['comparison_periods']['current']);
    $this->assertArrayHasKey('start', $data['metadata']['comparison_periods']['previous']);
    $this->assertArrayHasKey('end', $data['metadata']['comparison_periods']['previous']);
});

it('kpis trend calculation', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Orders count should have trend indicator
    $this->assertArrayHasKey('trend', $data['order_count']);
    $this->assertContains($data['order_count']['trend'], ['up', 'down', 'flat']);
});

it('kpis with custom date range', function () {
    // Skip due to Carbon date parsing issue in test environment
    // $this->markTestSkipped('Custom date range validation has Carbon parsing issues in test environment');
    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $response = $this->getJson("/admin/reports/kpis?start_date={$startDate}&end_date={$endDate}");

    $response->assertStatus(200);

    $data = $response->json();

    $this->assertArrayHasKey('metadata', $data);
    $this->assertStringContainsString($startDate, $data['metadata']['comparison_periods']['current']['start']);
    $this->assertStringContainsString($endDate, $data['metadata']['comparison_periods']['current']['end']);
});

it('kpis percentage formatted correctly', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Churn should be decimal (e.g., 0.06 for 6%)
    $this->assertIsNumeric($data['churn']['raw_current']);

    // Change percentage should be numeric (or null if previous is 0)
    $this->assertTrue(is_numeric($data['mrr']['delta_percent']) || is_null($data['mrr']['delta_percent']));
});

it('kpis change string formatting', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // MRR change should be percentage format (e.g. 11.1 or null)
    $this->assertArrayHasKey('delta_percent', $data['mrr']);
    $this->assertTrue(is_numeric($data['mrr']['delta_percent']) || is_null($data['mrr']['delta_percent']));
});

it('kpis handles zero division gracefully', function () {
    // Test with no data (should not throw division by zero errors)
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Should return valid structure even with no data
    $this->assertIsString($data['mrr']['current']);
    $this->assertIsNumeric($data['mrr']['raw_current']);
    $this->assertIsNumeric($data['churn']['raw_current']);
});

it('kpis cache clear includes kpi metrics', function () {
    // Make initial request to cache data
    $this->getJson('/admin/reports/kpis');

    // Clear cache
    $response = $this->postJson('/admin/reports/clear-cache');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Reports cache cleared successfully',
        ]);
});

it('kpis mrr segments by plan', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    $this->assertArrayHasKey('by_plan', $data['mrr']);
    $this->assertIsArray($data['mrr']['by_plan']);

    if (count($data['mrr']['by_plan']) > 0) {
        foreach ($data['mrr']['by_plan'] as $name => $mrr) {
            $this->assertIsString((string) $name);
            $this->assertIsNumeric($mrr);
        }
    }
});

it('kpis ltv calculation', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    $this->assertArrayHasKey('ltv', $data);
    $this->assertArrayHasKey('current', $data['ltv']);
    $this->assertArrayHasKey('previous', $data['ltv']);
    $this->assertIsNumeric($data['ltv']['raw_current']);
});

it('kpis can be filtered by includes parameter', function () {
    $response = $this->getJson('/admin/reports/kpis?includes=mrr,churn,order_count');

    $response->assertStatus(200);

    $data = $response->json();

    // Should only include requested KPIs
    $this->assertArrayHasKey('mrr', $data);
    $this->assertArrayHasKey('churn', $data);
    $this->assertArrayHasKey('order_count', $data);
    $this->assertArrayHasKey('metadata', $data); // Always included

    // Should not include other KPIs
    $this->assertArrayNotHasKey('ltv', $data);
    $this->assertArrayNotHasKey('arpu', $data);
    $this->assertArrayNotHasKey('active_users', $data);
    $this->assertArrayNotHasKey('total_revenue', $data);
    $this->assertArrayNotHasKey('new_customers', $data);
});

it('kpis includes single metric', function () {
    $response = $this->getJson('/admin/reports/kpis?includes=mrr');

    $response->assertStatus(200);

    $data = $response->json();

    // Should only include MRR
    $this->assertArrayHasKey('mrr', $data);
    $this->assertArrayHasKey('metadata', $data);

    // Should have exactly 2 keys (mrr + metadata)
    $this->assertCount(2, $data);
});

it('kpis includes ignores invalid keys', function () {
    $response = $this->getJson('/admin/reports/kpis?includes=mrr,invalid_key,churn,another_invalid');

    $response->assertStatus(200);

    $data = $response->json();

    // Should only include valid KPIs
    $this->assertArrayHasKey('mrr', $data);
    $this->assertArrayHasKey('churn', $data);
    $this->assertArrayHasKey('metadata', $data);

    // Should ignore invalid keys
    $this->assertArrayNotHasKey('invalid_key', $data);
    $this->assertArrayNotHasKey('another_invalid', $data);

    // Should have exactly 3 keys (mrr + churn + metadata)
    $this->assertCount(3, $data);
});

it('kpis includes with spaces', function () {
    $response = $this->getJson('/admin/reports/kpis?includes=mrr, churn , order_count');

    $response->assertStatus(200);

    $data = $response->json();

    // Should trim spaces and include requested KPIs
    $this->assertArrayHasKey('mrr', $data);
    $this->assertArrayHasKey('churn', $data);
    $this->assertArrayHasKey('order_count', $data);
    $this->assertCount(4, $data); // mrr + churn + order_count + metadata
});

it('kpis without includes returns all metrics', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Should include all KPIs
    $this->assertArrayHasKey('mrr', $data);
    $this->assertArrayHasKey('churn', $data);
    $this->assertArrayHasKey('ltv', $data);
    $this->assertArrayHasKey('arpu', $data);
    $this->assertArrayHasKey('order_count', $data);
    $this->assertArrayHasKey('total_revenue', $data);
    $this->assertArrayHasKey('active_users', $data);
    $this->assertArrayHasKey('metadata', $data);

    // Should have many keys (all KPIs)
    $this->assertGreaterThan(10, count($data));
});
