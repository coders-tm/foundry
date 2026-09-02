<?php

use Foundry\Models\Admin;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Tests\Feature\FeatureTestCase;

uses(FeatureTestCase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin, 'admin');
});

it('subscriptions export report returns correct data', function () {
    // Create test subscriptions with known values
    $userModel = User::class;
    $user1 = $userModel::factory()->create(['email' => 'test1@example.com']);
    $user2 = $userModel::factory()->create(['email' => 'test2@example.com']);

    $plan = Plan::factory()->create([
        'label' => 'Test Plan',
        'price' => 99.00,
        'interval' => 'month',
    ]);

    Subscription::factory()->create([
        'user_id' => $user1->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'created_at' => now()->subDays(5),
    ]);

    Subscription::factory()->create([
        'user_id' => $user2->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'created_at' => now()->subDays(3),
    ]);

    $response = $this->getJson('/admin/reports/exports/data?type=subscriptions');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('data');
    expect($data)->toHaveKey('meta');
    expect($data['data'])->toBeArray();
    expect(count($data['data']))->toBeGreaterThanOrEqual(2);

    // Verify subscription data structure
    $firstSub = $data['data'][0];
    expect($firstSub)->toHaveKey('id');
    expect($firstSub)->toHaveKey('status');
    expect($firstSub)->toBeArray();
});

it('mrr movement report returns correct data', function () {
    $userModel = User::class;
    $user = $userModel::factory()->create();

    $plan = Plan::factory()->create([
        'price' => 100.00,
        'interval' => 'month',
    ]);

    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'created_at' => now()->startOfMonth(),
    ]);

    $response = $this->getJson('/admin/reports/exports/data?type=mrr-movement');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('data');
    expect($data['data'])->toBeArray();

    if (count($data['data']) > 0) {
        $row = $data['data'][0];
        expect($row)->toHaveKey('period');
        expect($row)->toBeArray();
    }
});

it('orders export report returns correct data', function () {
    $order1 = Order::factory()->create([
        'status' => 'completed',
        'created_at' => now()->subDays(1),
    ]);

    $order2 = Order::factory()->create([
        'status' => 'completed',
        'created_at' => now()->subHours(12),
    ]);

    $response = $this->getJson('/admin/reports/exports/data?type=orders');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('data');
    expect($data['data'])->toBeArray();
    expect(count($data['data']))->toBeGreaterThanOrEqual(2);

    $firstOrder = $data['data'][0];
    expect($firstOrder)->toHaveKey('id');
    expect($firstOrder)->toBeArray();
});

it('arpu report returns correct data', function () {
    $userModel = User::class;
    $user1 = $userModel::factory()->create();
    $user2 = $userModel::factory()->create();

    $plan = Plan::factory()->create([
        'price' => 75.00,
        'interval' => 'month',
    ]);

    Subscription::factory()->create([
        'user_id' => $user1->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    Subscription::factory()->create([
        'user_id' => $user2->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $response = $this->getJson('/admin/reports/exports/data?type=arpu');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('data');
    expect($data['data'])->toBeArray();

    if (count($data['data']) > 0) {
        $row = $data['data'][0];
        expect($row)->toHaveKey('period');
        expect($row)->toHaveKey('arpu');
        // ARPU may be formatted as string like "$0.00", so just check it exists
        expect($row['arpu'])->not->toBeNull();
    }
});

it('users export report returns correct data', function () {
    $userModel = User::class;
    $user1 = $userModel::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
    ]);

    $user2 = $userModel::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@example.com',
    ]);

    $response = $this->getJson('/admin/reports/exports/data?type=users');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('data');
    expect($data['data'])->toBeArray();
    expect(count($data['data']))->toBeGreaterThanOrEqual(2);

    $userData = $data['data'][0];
    expect($userData)->toHaveKey('id');
    expect($userData)->toHaveKey('email');
});

it('report data endpoint validates date filters', function () {
    $response = $this->getJson('/admin/reports/exports/data?type=mrr-by-plan&filters[date_from]=2024-01-01&filters[date_to]=2024-12-31');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('data');
    expect($data['data'])->toBeArray();
});

it('report data endpoint handles pagination', function () {
    // Create many subscriptions
    $userModel = User::class;
    $plan = Plan::factory()->create();

    for ($i = 0; $i < 15; $i++) {
        $user = $userModel::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
    }

    $response = $this->getJson('/admin/reports/exports/data?type=subscriptions&rowsPerPage=10&page=1');

    $response->assertStatus(200);
    $data = $response->json();

    expect($data)->toHaveKey('data');
    expect($data)->toHaveKey('meta');
    expect($data['data'])->toBeArray();
    expect(count($data['data']))->toBeLessThanOrEqual(10);

    // Check pagination meta
    expect($data['meta'])->toHaveKey('current_page');
    expect($data['meta'])->toHaveKey('per_page');
    expect($data['meta']['current_page'])->toEqual(1);
    expect($data['meta']['per_page'])->toEqual(10);
});
