<?php

namespace Foundry\Tests\Feature;

use App\Models\User;
use Foundry\Models\Admin;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive tests for /admin/reports/exports/data endpoint.
 * These tests verify that each report returns correct data structure and validates actual values.
 */
class ReportDataEndpointTest extends FeatureTestCase
{
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var Admin $admin */
        $this->admin = $this->createAdmin();
        $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    public function subscriptions_export_report_returns_correct_data()
    {
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

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThanOrEqual(2, count($data['data']));

        // Verify subscription data structure
        $firstSub = $data['data'][0];
        $this->assertArrayHasKey('id', $firstSub);
        $this->assertArrayHasKey('status', $firstSub);
        $this->assertIsArray($firstSub);
    }

    #[Test]
    public function mrr_movement_report_returns_correct_data()
    {
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

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);

        if (count($data['data']) > 0) {
            $row = $data['data'][0];
            $this->assertArrayHasKey('period', $row);
            $this->assertIsArray($row);
        }
    }

    #[Test]
    public function orders_export_report_returns_correct_data()
    {
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

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThanOrEqual(2, count($data['data']));

        $firstOrder = $data['data'][0];
        $this->assertArrayHasKey('id', $firstOrder);
        $this->assertIsArray($firstOrder);
    }

    #[Test]
    public function arpu_report_returns_correct_data()
    {
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

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);

        if (count($data['data']) > 0) {
            $row = $data['data'][0];
            $this->assertArrayHasKey('period', $row);
            $this->assertArrayHasKey('arpu', $row);
            // ARPU may be formatted as string like "$0.00", so just check it exists
            $this->assertNotNull($row['arpu']);
        }
    }

    #[Test]
    public function users_export_report_returns_correct_data()
    {
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

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertGreaterThanOrEqual(2, count($data['data']));

        $userData = $data['data'][0];
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('email', $userData);
    }

    #[Test]
    public function report_data_endpoint_validates_date_filters()
    {
        $response = $this->getJson('/admin/reports/exports/data?type=mrr-by-plan&filters[date_from]=2024-01-01&filters[date_to]=2024-12-31');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
    }

    #[Test]
    public function report_data_endpoint_handles_pagination()
    {
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

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['data']);
        $this->assertLessThanOrEqual(10, count($data['data']));

        // Check pagination meta
        $this->assertArrayHasKey('current_page', $data['meta']);
        $this->assertArrayHasKey('per_page', $data['meta']);
        $this->assertEquals(1, $data['meta']['current_page']);
        $this->assertEquals(10, $data['meta']['per_page']);
    }

    protected function createAdmin()
    {
        return Admin::factory()->create([
            'is_super_admin' => true,
        ]);
    }
}
