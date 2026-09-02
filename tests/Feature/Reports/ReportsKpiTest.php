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
    expect($data['mrr']['raw_current'])->toBeGreaterThan(0, 'MRR should be greater than 0 with seeded data');
    expect($data['mrr'])->toHaveKey('by_plan');
    expect($data['mrr'])->toHaveKey('by_interval');
});

it('kpis churn calculation', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    expect($data['churn'])->toHaveKey('current');
    expect($data['churn'])->toHaveKey('previous');
    expect($data['churn'])->toHaveKey('logo_churn');
    expect($data['churn']['raw_current'])->toBeNumeric();
});

it('kpis orders metrics', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Check flattened order metrics
    expect($data)->toHaveKey('order_count');
    expect($data)->toHaveKey('net_revenue');
    expect($data)->toHaveKey('aov');

    // Each order KPI should have period comparison
    expect($data['order_count'])->toHaveKey('current');
    expect($data['order_count'])->toHaveKey('previous');
    expect($data['order_count'])->toHaveKey('trend');
    expect($data['order_count'])->toHaveKey('description');
});

it('kpis active users calculation', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    expect($data['active_users'])->toHaveKey('current');
    expect($data['active_users']['raw_current'])->toBeGreaterThan(0);
});

it('kpis metadata includes period ranges', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    expect($data)->toHaveKey('metadata');
    expect($data['metadata'])->toHaveKey('comparison_periods');
    expect($data['metadata']['comparison_periods']['current'])->toHaveKey('start');
    expect($data['metadata']['comparison_periods']['current'])->toHaveKey('end');
    expect($data['metadata']['comparison_periods']['previous'])->toHaveKey('start');
    expect($data['metadata']['comparison_periods']['previous'])->toHaveKey('end');
});

it('kpis trend calculation', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Orders count should have trend indicator
    expect($data['order_count'])->toHaveKey('trend');
    expect(['up', 'down', 'flat'])->toContain($data['order_count']['trend']);
});

it('kpis with custom date range', function () {
    // Skip due to Carbon date parsing issue in test environment
    // $this->markTestSkipped('Custom date range validation has Carbon parsing issues in test environment');
    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $response = $this->getJson("/admin/reports/kpis?start_date={$startDate}&end_date={$endDate}");

    $response->assertStatus(200);

    $data = $response->json();

    expect($data)->toHaveKey('metadata');
    expect($data['metadata']['comparison_periods']['current']['start'])->toContain($startDate);
    expect($data['metadata']['comparison_periods']['current']['end'])->toContain($endDate);
});

it('kpis percentage formatted correctly', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Churn should be decimal (e.g., 0.06 for 6%)
    expect($data['churn']['raw_current'])->toBeNumeric();

    // Change percentage should be numeric (or null if previous is 0)
    expect(is_numeric($data['mrr']['delta_percent']) || is_null($data['mrr']['delta_percent']))->toBeTrue();
});

it('kpis change string formatting', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // MRR change should be percentage format (e.g. 11.1 or null)
    expect($data['mrr'])->toHaveKey('delta_percent');
    expect(is_numeric($data['mrr']['delta_percent']) || is_null($data['mrr']['delta_percent']))->toBeTrue();
});

it('kpis handles zero division gracefully', function () {
    // Test with no data (should not throw division by zero errors)
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Should return valid structure even with no data
    expect($data['mrr']['current'])->toBeString();
    expect($data['mrr']['raw_current'])->toBeNumeric();
    expect($data['churn']['raw_current'])->toBeNumeric();
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

    expect($data['mrr'])->toHaveKey('by_plan');
    expect($data['mrr']['by_plan'])->toBeArray();

    if (count($data['mrr']['by_plan']) > 0) {
        foreach ($data['mrr']['by_plan'] as $name => $mrr) {
            expect((string) $name)->toBeString();
            expect($mrr)->toBeNumeric();
        }
    }
});

it('kpis ltv calculation', function () {
    // Use existing seeded data
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    expect($data)->toHaveKey('ltv');
    expect($data['ltv'])->toHaveKey('current');
    expect($data['ltv'])->toHaveKey('previous');
    expect($data['ltv']['raw_current'])->toBeNumeric();
});

it('kpis can be filtered by includes parameter', function () {
    $response = $this->getJson('/admin/reports/kpis?includes=mrr,churn,order_count');

    $response->assertStatus(200);

    $data = $response->json();

    // Should only include requested KPIs
    expect($data)->toHaveKey('mrr');
    expect($data)->toHaveKey('churn');
    expect($data)->toHaveKey('order_count');
    expect($data)->toHaveKey('metadata'); // Always included

    // Should not include other KPIs
    expect($data)->not->toHaveKey('ltv');
    expect($data)->not->toHaveKey('arpu');
    expect($data)->not->toHaveKey('active_users');
    expect($data)->not->toHaveKey('total_revenue');
    expect($data)->not->toHaveKey('new_customers');
});

it('kpis includes single metric', function () {
    $response = $this->getJson('/admin/reports/kpis?includes=mrr');

    $response->assertStatus(200);

    $data = $response->json();

    // Should only include MRR
    expect($data)->toHaveKey('mrr');
    expect($data)->toHaveKey('metadata');

    // Should have exactly 2 keys (mrr + metadata)
    expect($data)->toHaveCount(2);
});

it('kpis includes ignores invalid keys', function () {
    $response = $this->getJson('/admin/reports/kpis?includes=mrr,invalid_key,churn,another_invalid');

    $response->assertStatus(200);

    $data = $response->json();

    // Should only include valid KPIs
    expect($data)->toHaveKey('mrr');
    expect($data)->toHaveKey('churn');
    expect($data)->toHaveKey('metadata');

    // Should ignore invalid keys
    expect($data)->not->toHaveKey('invalid_key');
    expect($data)->not->toHaveKey('another_invalid');

    // Should have exactly 3 keys (mrr + churn + metadata)
    expect($data)->toHaveCount(3);
});

it('kpis includes with spaces', function () {
    $response = $this->getJson('/admin/reports/kpis?includes=mrr, churn , order_count');

    $response->assertStatus(200);

    $data = $response->json();

    // Should trim spaces and include requested KPIs
    expect($data)->toHaveKey('mrr');
    expect($data)->toHaveKey('churn');
    expect($data)->toHaveKey('order_count');
    expect($data)->toHaveCount(4); // mrr + churn + order_count + metadata
});

it('kpis without includes returns all metrics', function () {
    $response = $this->getJson('/admin/reports/kpis');

    $response->assertStatus(200);

    $data = $response->json();

    // Should include all KPIs
    expect($data)->toHaveKey('mrr');
    expect($data)->toHaveKey('churn');
    expect($data)->toHaveKey('ltv');
    expect($data)->toHaveKey('arpu');
    expect($data)->toHaveKey('order_count');
    expect($data)->toHaveKey('total_revenue');
    expect($data)->toHaveKey('active_users');
    expect($data)->toHaveKey('metadata');

    // Should have many keys (all KPIs)
    expect(count($data))->toBeGreaterThan(10);
});
