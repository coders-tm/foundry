<?php

use Foundry\Models\Tax;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Workbench\App\Models\Admin;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

uses(FeatureTestCase::class)
    ->use(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create(['is_super_admin' => true]);
});

it('returns default tax even if empty array provided', function () {
    config(['app.country' => 'United States']);
    Tax::truncate();
    Tax::create(['country' => 'United States', 'code' => 'US', 'state' => '*', 'label' => 'Sales Tax', 'rate' => 10]);

    $data = ['collect_tax' => true, 'line_items' => [['title' => 'Item', 'price' => 100, 'quantity' => 1, 'taxable' => true]], 'tax_lines' => []];

    $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.orders.calculator'), $data);
    $response->assertStatus(200);
    $response->assertJsonPath('tax_lines.0.label', 'Sales Tax');
});

it('returns default tax for customer without address', function () {
    config(['app.country' => 'United States']);
    Tax::truncate();
    Tax::create(['country' => 'United States', 'code' => 'US', 'state' => '*', 'label' => 'Global Tax', 'rate' => 5]);

    $user = User::create(['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'password' => 'password', 'status' => 'active']);

    $data = ['customer' => ['id' => $user->id], 'collect_tax' => true, 'line_items' => [['title' => 'Item', 'price' => 200, 'quantity' => 1, 'taxable' => true]]];

    $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.orders.calculator'), $data);
    $response->assertStatus(200);
    $response->assertJsonPath('tax_lines.0.label', 'Global Tax');
    $this->assertEquals(10, $response->json('tax_total'));
});

it('recalculates for existing order', function () {
    $order = Order::create(['collect_tax' => false, 'sub_total' => 100, 'grand_total' => 100, 'status' => 'pending', 'payment_status' => 'pending', 'line_items' => [['title' => 'Item', 'price' => 100, 'quantity' => 1, 'taxable' => true]]]);

    config(['app.country' => 'United States']);
    Tax::truncate();
    Tax::create(['country' => 'United States', 'code' => 'US', 'state' => '*', 'label' => 'Re-calc Tax', 'rate' => 15]);

    $data = ['id' => $order->id, 'collect_tax' => true, 'line_items' => [['title' => 'Item', 'price' => 100, 'quantity' => 1, 'taxable' => true]]];

    $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.orders.calculator'), $data);
    $response->assertStatus(200);
    $this->assertEquals(15, $response->json('tax_total'));
    $this->assertEquals(115, $response->json('grand_total'));
    $response->assertJsonPath('tax_lines.0.label', 'Re-calc Tax');
});

it('returns rest of world tax', function () {
    Tax::truncate();
    Tax::create(['country' => 'Any', 'code' => '*', 'state' => '*', 'label' => 'Global Tax', 'rate' => 7]);

    $data = ['collect_tax' => true, 'line_items' => [['title' => 'Item', 'price' => 100, 'quantity' => 1, 'taxable' => true]]];

    $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.orders.calculator'), $data);
    $response->assertStatus(200);
    $response->assertJsonPath('tax_lines.0.label', 'Global Tax');
    $this->assertEquals(7, $response->json('tax_total'));
});

it('returns discount correctly', function () {
    $data = ['line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]], 'discount' => ['type' => 'fixed_amount', 'value' => 20, 'description' => 'Promo Code']];

    $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.orders.calculator'), $data);
    $response->assertStatus(200);
    $response->assertJsonPath('discount.type', 'fixed_amount');
    $this->assertEquals(20, $response->json('discount.value'));
    $this->assertEquals(20, $response->json('discount_total'));
    $this->assertEquals(80, $response->json('grand_total'));
});
