<?php

namespace Foundry\Tests\Feature\Order;

use Foundry\Models\Tax;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Workbench\App\Models\Admin;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

class OrderCalculatorTest extends FeatureTestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create([
            'is_super_admin' => true,
        ]);
    }

    public function test_calculator_returns_default_tax_even_if_empty_array_provided(): void
    {
        // Setup default tax
        config(['app.country' => 'United States']);
        Tax::truncate();
        Tax::create([
            'country' => 'United States',
            'code' => 'US',
            'state' => '*',
            'label' => 'Sales Tax',
            'rate' => 10,
        ]);

        $data = [
            'collect_tax' => true,
            'line_items' => [['title' => 'Item', 'price' => 100, 'quantity' => 1, 'taxable' => true]],
            'tax_lines' => [], // Explicit empty array from frontend
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.calculator'), $data);

        $response->assertStatus(200);

        // Check if tax_lines are returned
        $response->assertJsonPath('tax_lines.0.label', 'Sales Tax');
    }

    public function test_calculator_returns_default_tax_for_customer_without_address(): void
    {
        // Setup default tax
        config(['app.country' => 'United States']);
        Tax::truncate();
        Tax::create([
            'country' => 'United States',
            'code' => 'US',
            'state' => '*',
            'label' => 'Global Tax',
            'rate' => 5,
        ]);

        $user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'status' => 'active',
        ]);

        $data = [
            'customer' => ['id' => $user->id],
            'collect_tax' => true,
            'line_items' => [
                ['title' => 'Item', 'price' => 200, 'quantity' => 1, 'taxable' => true],
            ],
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.calculator'), $data);

        // dump($response->json());

        $response->assertStatus(200);
        $response->assertJsonPath('tax_lines.0.label', 'Global Tax');
        $this->assertEquals(10, $response->json('tax_total')); // 5% of 200
    }

    public function test_calculator_recalculates_for_existing_order(): void
    {
        $order = Order::create([
            'collect_tax' => false, // Initially false
            'sub_total' => 100,
            'grand_total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
            'line_items' => [
                ['title' => 'Item', 'price' => 100, 'quantity' => 1, 'taxable' => true],
            ],
        ]);

        // Setup default tax
        config(['app.country' => 'United States']);
        Tax::truncate();
        Tax::create([
            'country' => 'United States',
            'code' => 'US',
            'state' => '*',
            'label' => 'Re-calc Tax',
            'rate' => 15,
        ]);

        $data = [
            'id' => $order->id,
            'collect_tax' => true, // Turn it on
            'line_items' => [
                ['title' => 'Item', 'price' => 100, 'quantity' => 1, 'taxable' => true],
            ],
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.calculator'), $data);

        $response->assertStatus(200);
        $this->assertEquals(15, $response->json('tax_total'));
        $this->assertEquals(115, $response->json('grand_total'));
        $response->assertJsonPath('tax_lines.0.label', 'Re-calc Tax');
    }

    public function test_calculator_returns_rest_of_world_tax(): void
    {
        Tax::truncate();
        Tax::create([
            'country' => 'Any',
            'code' => '*',
            'state' => '*',
            'label' => 'Global Tax',
            'rate' => 7,
        ]);

        $data = [
            'collect_tax' => true,
            'line_items' => [['title' => 'Item', 'price' => 100, 'quantity' => 1, 'taxable' => true]],
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.calculator'), $data);

        $response->assertStatus(200);
        $response->assertJsonPath('tax_lines.0.label', 'Global Tax');
        $this->assertEquals(7, $response->json('tax_total'));
    }

    public function test_calculator_returns_discount_correctly(): void
    {
        $data = [
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'price' => 100,
                    'quantity' => 1,
                ],
            ],
            'discount' => [
                'type' => 'fixed_amount',
                'value' => 20,
                'description' => 'Promo Code',
            ],
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.calculator'), $data);

        $response->assertStatus(200);
        $response->assertJsonPath('discount.type', 'fixed_amount');
        $this->assertEquals(20, $response->json('discount.value'));
        $this->assertEquals(20, $response->json('discount_total'));
        $this->assertEquals(80, $response->json('grand_total'));
    }
}
