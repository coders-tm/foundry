<?php

namespace Foundry\Tests\Feature\Order;

use Foundry\Enum\OrderStatus;
use Foundry\Enum\PaymentStatus;
use Foundry\Models\Order;
use Foundry\Models\Order\Customer;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Workbench\App\Models\Admin;
use Workbench\App\Models\User;

class OrderControllerTest extends FeatureTestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $user;


    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->admin()->create([
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->user = User::factory()->create();
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson(route('admin.orders.index'))
            ->assertUnauthorized();
    }

    public function test_index_renders_json_data(): void
    {
        Order::factory()->count(3)->create();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.index'))
            ->assertSuccessful()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_can_filter_by_status(): void
    {
        Order::factory()->create(['status' => OrderStatus::PENDING_PAYMENT]);
        Order::factory()->create(['status' => OrderStatus::COMPLETED]);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.index', ['status' => OrderStatus::PENDING_PAYMENT->value]))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['status' => OrderStatus::PENDING_PAYMENT->value]);
    }

    public function test_index_can_filter_by_payment_status(): void
    {
        Order::factory()->create(['payment_status' => PaymentStatus::PAID]);
        Order::factory()->create(['payment_status' => PaymentStatus::PAYMENT_PENDING]);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.index', ['payment_status' => PaymentStatus::PAID->value]))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['payment_status' => PaymentStatus::PAID->value]);
    }

    public function test_index_can_show_trashed_orders(): void
    {
        $order = Order::factory()->create();
        $order->delete();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.index', ['deleted' => true]))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_store_creates_new_order(): void
    {
        $paymentMethod = PaymentMethod::factory()->create();
        $customer = Customer::factory()->create();

        $orderData = [
            'customer_id' => $customer->id,
            'status' => OrderStatus::PENDING_PAYMENT->value,
            'payment_status' => PaymentStatus::PAYMENT_PENDING->value,
            'sub_total' => 100,
            'tax_total' => 10,
            'grand_total' => 110,
            'line_items' => [
                [
                    'title' => 'Test Item',
                    'quantity' => 1,
                    'price' => 100,
                ],
            ],
        ];

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.store'), $orderData)
            ->assertStatus(201)
            ->assertJsonFragment(['message' => __('Order has been created successfully.')]);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'grand_total' => 110,
        ]);
    }

    public function test_show_returns_order_details(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.show', $order))
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_update_modifies_existing_order(): void
    {
        $order = Order::factory()->create(['note' => 'Old Note']);

        $this->actingAs($this->admin, 'admin')
            ->patchJson(route('admin.orders.update', $order), ['note' => 'New Note'])
            ->assertSuccessful()
            ->assertJsonFragment(['note' => 'New Note']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'note' => 'New Note',
        ]);
    }

    public function test_destroy_deletes_order(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->deleteJson(route('admin.orders.destroy', $order))
            ->assertSuccessful();

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_restore_reinstates_deleted_order(): void
    {
        $order = Order::factory()->create();
        $order->delete();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.restore', $order->id))
            ->assertSuccessful();

        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_bulk_destroy_deletes_multiple_orders(): void
    {
        $orders = Order::factory()->count(3)->create();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.bulk-destroy'), [
                'items' => $orders->pluck('id')->toArray(),
            ])
            ->assertSuccessful();

        foreach ($orders as $order) {
            $this->assertSoftDeleted('orders', ['id' => $order->id]);
        }
    }

    public function test_bulk_restore_reinstates_multiple_orders(): void
    {
        $orders = Order::factory()->count(3)->create();
        $orders->each->delete();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.bulk-restore'), [
                'items' => $orders->pluck('id')->toArray(),
            ])
            ->assertSuccessful();

        foreach ($orders as $order) {
            $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
        }
    }

    public function test_cancel_updates_order_status(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING_PAYMENT]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.cancel', $order), ['reason' => 'Test reason'])
            ->assertSuccessful()
            ->assertJsonFragment(['status' => OrderStatus::CANCELLED->value]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
            'cancel_reason' => 'Test reason',
        ]);
    }

    public function test_mark_as_paid_updates_statuses(): void
    {
        $paymentMethod = PaymentMethod::factory()->create();
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING_PAYMENT,
            'payment_status' => PaymentStatus::PAYMENT_PENDING,
            'grand_total' => 100,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.mark-as-paid', $order), [
                'payment_method' => $paymentMethod->id,
            ])
            ->assertSuccessful()
            ->assertJsonFragment(['payment_status' => PaymentStatus::PAID->value]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::PAID->value,
        ]);

        $this->assertDatabaseHas('payments', [
            'paymentable_id' => $order->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => PaymentStatus::COMPLETED->value,
        ]);
    }

    public function test_update_status_modifies_status(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING_PAYMENT]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.update-status', $order), [
                'status' => OrderStatus::COMPLETED->value,
            ])
            ->assertSuccessful()
            ->assertJsonFragment(['status' => OrderStatus::COMPLETED->value]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::COMPLETED->value,
        ]);
    }

    public function test_logs_returns_order_logs(): void
    {
        $order = Order::factory()->create();
        $order->logs()->create(['message' => 'Test log', 'type' => 'note']);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.logs', $order))
            ->assertSuccessful()
            ->assertJsonCount(1);
    }

    public function test_store_log_creates_new_log(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.orders.store-log', $order), [
                'message' => 'New log message',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('logs', [
            'logable_id' => $order->id,
            'message' => 'New log message',
        ]);
    }

    public function test_show_subscription_invoice_includes_line_items(): void
    {
        $plan = Plan::factory()->create(['price' => 50, 'trial_days' => 0]);
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.subscriptions.store'), [
                'user_id' => $user->id,
                'plan' => $plan->id,
                'starts_at' => now()->toDateTimeString(),
                'generate_invoice' => true,
                'mark_as_paid' => true,
                'payment_method' => $paymentMethod->id,
            ])
            ->assertStatus(201);

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertNotNull($subscription);

        $order = Order::latest()->first();
        $this->assertGreaterThan(0, $order->line_items()->count());
        $this->assertEquals(format_amount(55), $order->total());
        $this->assertEquals(PaymentStatus::PAID, $order->payment_status);
    }

    public function test_export_returns_success_message(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.export'))
            ->assertSuccessful()
            ->assertJsonFragment(['message' => __('Order export has been started successfully.')]);
    }
}
