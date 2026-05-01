<?php

namespace Foundry\Tests\Feature\Order;

use Foundry\Enum\OrderStatus;
use Foundry\Enum\PaymentStatus;
use Foundry\Foundry;
use Foundry\Models\Admin;
use Foundry\Models\Order;
use Foundry\Models\Order\Customer;
use Foundry\Models\Order\TaxLine;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Notifications\OrderInvoiceNotification;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class OrderControllerTest extends FeatureTestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->user = User::factory()->create();
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('admin.orders.index'))
            ->assertRedirect();
    }

    public function test_index_page(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.index'))
            ->assertSuccessful();
    }

    public function test_store_creates_item_and_redirects(): void
    {
        $user = User::factory()->create();
        $data = [
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'note' => 'Test order note',
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'price' => 100,
                    'quantity' => 1,
                ],
            ],
            'sub_total' => 100,
            'grand_total' => 100,
        ];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.store'), $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['customer_id' => $data['customer_id'], 'status' => $data['status']]);

        $order = Order::where('customer_id', $user->id)->first();
        $this->assertNotNull($order->number, 'Order number should not be null');

        $this->assertDatabaseHas('line_items', [
            'title' => 'Product 1',
            'itemable_id' => $order->id,
            'itemable_type' => 'Order',
        ]);
    }

    public function test_store_marks_as_paid_if_payment_method_provided(): void
    {
        $user = User::factory()->create();
        PaymentMethod::factory()->manual()->create(['id' => 'cash']);
        $data = [
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'payment_method' => 'cash',
            'line_items' => [
                ['title' => 'Product 1', 'price' => 100, 'quantity' => 1],
            ],
        ];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.store'), $data)
            ->assertRedirect();

        $order = Order::where('customer_id', $user->id)->first();
        $this->assertTrue($order->is_paid);
    }

    public function test_store_sends_invoice_if_invoice_data_provided(): void
    {
        Notification::fake();
        \Foundry\Models\Notification::factory()->create([
            'type' => 'user:invoice-sent',
            'subject' => 'Invoice for Order {{ $order->number }}',
            'content' => 'Hello',
        ]);

        $user = User::factory()->create();
        $data = [
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'invoice_data' => [
                'to' => 'custom@example.com',
                'subject' => 'Custom Subject',
                'message' => 'Custom Message',
            ],
            'line_items' => [
                ['title' => 'Product 1', 'price' => 100, 'quantity' => 1],
            ],
        ];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.store'), $data)
            ->assertRedirect();

        $order = Order::where('customer_id', $user->id)->first();

        Notification::assertSentTo(
            new Customer(['id' => $order->customer_id]),
            OrderInvoiceNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->email === 'custom@example.com'
        );

        $this->assertDatabaseHas('logs', [
            'type' => 'invoice_sent',
            'logable_id' => $order->id,
            'logable_type' => 'Order',
        ]);
    }

    public function test_update_modifies_item(): void
    {
        $item = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $data = [
            'status' => OrderStatus::COMPLETED->value,
            'line_items' => [
                [
                    'title' => 'Updated Product',
                    'price' => 150,
                    'quantity' => 1,
                ],
            ],
        ];

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.orders.update', $item), $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertEquals($data['status'], $item->refresh()->status->value);
        $this->assertDatabaseHas('line_items', [
            'itemable_id' => $item->id,
            'itemable_type' => 'Order',
            'title' => 'Updated Product',
        ]);
    }

    public function test_cancel_marks_order_as_cancelled(): void
    {
        $item = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.cancel', $item))
            ->assertRedirect();

        $this->assertEquals(OrderStatus::CANCELLED, $item->refresh()->status);
    }

    public function test_mark_as_paid_updates_payment_status(): void
    {
        $item = Order::factory()->create(['payment_status' => PaymentStatus::PAYMENT_PENDING]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.mark-as-paid', $item))
            ->assertRedirect();

        $this->assertTrue($item->refresh()->is_paid);
    }

    public function test_send_invoice_sends_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'original@example.com']);
        $item = Order::factory()->create(['customer_id' => $user->id]);

        \Foundry\Models\Notification::factory()->create([
            'type' => 'user:invoice-sent',
            'subject' => 'Invoice for Order {{ $order->number }}',
            'content' => 'Hello',
        ]);

        // Test sending to default email
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.send-invoice', $item))
            ->assertRedirect();

        Notification::assertSentTo(
            new Customer(['id' => $item->customer_id]),
            OrderInvoiceNotification::class
        );

        // Test sending to custom email
        $customEmail = 'custom@example.com';
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.send-invoice', $item), ['to' => $customEmail])
            ->assertRedirect();

        Notification::assertSentTo(
            new Customer(['id' => $item->customer_id]),
            OrderInvoiceNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->email === $customEmail
        );
    }

    public function test_download_invoice_returns_streamed_response(): void
    {
        $user = User::factory()->create();
        $item = Order::factory()->create(['customer_id' => $user->id]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.download-invoice', $item))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_refund_processes_refund(): void
    {
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->manual()->create();
        $item = Order::factory()->create([
            'customer_id' => $user->id,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::COMPLETED,
            'grand_total' => 100,
            'paid_total' => 100,
        ]);

        // Create a completed payment record with payment method
        $item->payments()->create([
            'transaction_id' => 'txn_123',
            'amount' => 100,
            'status' => Payment::STATUS_COMPLETED,
            'processed_at' => now(),
            'currency' => 'USD',
            'payment_method_id' => $paymentMethod->id,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.refund', $item), ['to_wallet' => true])
            ->assertRedirect();

        $this->assertEquals(OrderStatus::REFUNDED, $item->refresh()->status);
    }

    public function test_show_subscription_invoice_includes_line_items(): void
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 19.99, 'trial_days' => 0]);
        $paymentMethod = PaymentMethod::factory()->create(['provider' => 'cash']);

        // Create a subscription with an invoice via the admin route
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.subscriptions.store'), [
                'user_id' => $user->id,
                'plan' => $plan->id,
                'starts_at' => now()->toDateTimeString(),
                'generate_invoice' => true,
                'mark_as_paid' => true,
                'payment_method' => $paymentMethod->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $subscription = Foundry::$subscriptionModel::where('user_id', $user->id)->first();
        $this->assertNotNull($subscription, 'Subscription should exist');
        $subscription->refresh();

        $invoice = $subscription->latestInvoice;
        $this->assertNotNull($invoice, 'Subscription invoice should exist');
        $this->assertEquals('Subscription', $invoice->orderable_type);

        // Assert line_items are stored with the correct itemable_type
        $this->assertDatabaseHas('line_items', [
            'itemable_id' => $invoice->id,
            'itemable_type' => 'Order',
        ]);

        // Load the invoice via admin.orders.show and assert line_items are present
        $response = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.show', $invoice->id))
            ->assertSuccessful();

        // Basic JSON structure checks
        $response->assertJsonStructure([
            'id',
            'line_items' => [
                '*' => [
                    'title',
                    'price',
                ],
            ],
        ]);

        $data = $response->json();

        // Ensure there's exactly one line item
        $this->assertCount(1, $data['line_items']);

        $lineItem = $data['line_items'][0];

        // Validate price and title type
        $this->assertEquals(19.99, $lineItem['price']);
        $this->assertIsString($lineItem['title']);
    }

    public function test_store_with_fixed_discount_creates_discount_line(): void
    {
        $user = User::factory()->create();
        $data = [
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'price' => 100,
                    'quantity' => 1,
                ],
            ],
            'discount' => [
                'type' => 'fixed_amount',
                'value' => 10,
                'description' => 'Test discount',
            ],
            'sub_total' => 100,
            'discount_total' => 10,
            'grand_total' => 90,
        ];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.store'), $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order = Foundry::$orderModel::where('customer_id', $user->id)->first();
        $this->assertNotNull($order, 'Order should be created');
        $this->assertEquals(10, $order->discount_total, 'discount_total should be stored');

        // Check if discount line is created
        $this->assertDatabaseHas('discount_lines', [
            'discountable_id' => $order->id,
            'discountable_type' => 'Order',
            'type' => 'fixed_amount',
            'value' => 10,
            'description' => 'Test discount',
        ]);

        // Verify discount relationship is accessible
        $order->refresh();
        $this->assertNotNull($order->discount, 'Order should have discount relationship');
        $this->assertEquals('fixed_amount', $order->discount->type);
        $this->assertEquals(10, $order->discount->value);
        $this->assertEquals('Test discount', $order->discount->description);
    }

    public function test_store_with_percentage_discount_creates_discount_line(): void
    {
        $user = User::factory()->create();
        $data = [
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'price' => 100,
                    'quantity' => 1,
                ],
            ],
            'discount' => [
                'type' => 'percentage',
                'value' => 10,
                'description' => '10% off',
            ],
            'sub_total' => 100,
            'discount_total' => 10,
            'grand_total' => 90,
        ];

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.store'), $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order = Foundry::$orderModel::where('customer_id', $user->id)->first();
        $this->assertNotNull($order, 'Order should be created');
        $this->assertEquals(10, $order->discount_total, 'discount_total should be 10 (10% of 100)');

        // Check if discount line is created
        $this->assertDatabaseHas('discount_lines', [
            'discountable_id' => $order->id,
            'discountable_type' => 'Order',
            'type' => 'percentage',
            'value' => 10,
            'description' => '10% off',
        ]);

        // Verify discount relationship
        $order->refresh();
        $this->assertNotNull($order->discount, 'Order should have discount relationship');
        $this->assertEquals('percentage', $order->discount->type);
        $this->assertEquals(10, $order->discount->value);
    }

    public function test_update_with_discount_creates_or_updates_discount_line(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'discount_total' => 0,
            'sub_total' => 100,
            'tax_total' => 0,
            'grand_total' => 100,
        ]);
        $order->line_items()->create([
            'title' => 'Product 1',
            'price' => 100,
            'quantity' => 1,
        ]);

        // Update order with discount
        $data = [
            'status' => 'pending',
            'sub_total' => 100,
            'tax_total' => 0,
            'discount_total' => 15,
            'grand_total' => 85,
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'price' => 100,
                    'quantity' => 1,
                ],
            ],
            'discount' => [
                'type' => 'fixed_amount',
                'value' => 15,
                'description' => 'Updated discount',
            ],
        ];

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.orders.update', $order), $data)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(15, $order->discount_total, 'discount_total should be updated');

        // Verify discount line is created or updated in database
        $this->assertDatabaseHas('discount_lines', [
            'discountable_id' => $order->id,
            'discountable_type' => 'Order',
            'type' => 'fixed_amount',
            'value' => 15,
            'description' => 'Updated discount',
        ]);
    }

    public function test_show_order_includes_all_necessary_relationships(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'price' => 100,
                    'quantity' => 1,
                    'taxable' => true,
                ],
            ],
            'discount' => [
                'type' => 'fixed_amount',
                'value' => 20,
                'description' => 'Test discount',
            ],
            'tax_lines' => [
                [
                    'label' => 'Sales Tax',
                    'rate' => 10,
                ],
            ],
            'contact' => [
                'email' => 'test@example.com',
                'phone_number' => '1234567890',
            ],
        ]);

        // Request JSON and assert the order and all relationships are present in the response
        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.orders.show', $order))
            ->assertSuccessful()
            ->assertJsonStructure([
                'id',
                'line_items',
                'tax_lines',
                'discount',
                'contact',
            ])
            ->assertJsonPath('discount.value', '20.00')
            ->assertJsonPath('tax_lines.0.label', 'Sales Tax')
            ->assertJsonPath('tax_lines.0.amount', '8.00');
    }

    public function test_order_create_calculates_correctly_with_compounded_taxes_and_discount(): void
    {
        $user = User::factory()->create();

        // Multi-product, discount, and compounded tax scenario
        // Prod 1: 100 (taxable)
        // Prod 2: 100 (taxable)
        // Subtotal: 200
        // Discount: 10% -> 20
        // Taxable Base: 180
        // Tax 1 (Default): 10% -> 18
        // Tax 2 (Compounded): 5% -> 5% of (180 + 18) = 9.90
        // Total Tax: 27.90
        // Grand Total: 180 + 27.90 = 207.90

        $order = Order::create([
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'line_items' => [
                ['title' => 'Product 1', 'price' => 100, 'quantity' => 1, 'taxable' => true],
                ['title' => 'Product 2', 'price' => 100, 'quantity' => 1, 'taxable' => true],
            ],
            'discount' => [
                'type' => 'percentage',
                'value' => 10,
            ],
            'tax_lines' => [
                ['label' => 'GST', 'rate' => 10, 'type' => 'normal'],
                ['label' => 'Cess', 'rate' => 5, 'type' => 'compounded'],
            ],
        ]);

        $this->assertEquals(200.00, (float) $order->sub_total);
        $this->assertEquals(20.00, (float) $order->discount_total);
        $this->assertEquals(27.90, (float) $order->tax_total);
        $this->assertEquals(207.90, (float) $order->grand_total);

        $this->assertCount(2, $order->tax_lines);
        $this->assertEquals(18.00, (float) $order->tax_lines->where('label', 'GST')->first()->amount);
        $this->assertEquals(9.90, (float) $order->tax_lines->where('label', 'Cess')->first()->amount);
    }

    public function test_order_update_recalculates_correctly_when_data_changes(): void
    {
        $user = User::factory()->create();
        $order = Order::create([
            'customer_id' => $user->id,
            'line_items' => [
                ['title' => 'Initial Item', 'price' => 100, 'quantity' => 1],
            ],
            'collect_tax' => false, // Ensure no default taxes are applied
        ]);

        $this->assertEquals(100.00, (float) $order->grand_total);

        // Update with new item and a tax
        $order->update([
            'line_items' => [
                ['title' => 'New Item', 'price' => 200, 'quantity' => 1, 'taxable' => true],
            ],
            'tax_lines' => [
                ['label' => 'Updated Tax', 'rate' => 10],
            ],
        ]);

        $order->refresh();
        $order->load(['tax_lines', 'line_items']);

        $this->assertEquals(200.00, (float) $order->sub_total);
        $this->assertEquals(20.00, (float) $order->tax_total);
        $this->assertEquals(220.00, (float) $order->grand_total);
        $this->assertCount(1, $order->line_items);
        $this->assertEquals('New Item', $order->line_items->first()->title);

        $this->assertCount(1, $order->tax_lines);
        $this->assertEquals(20.00, (float) $order->tax_lines->first()->amount);
    }

    public function test_store_endpoint_calculates_correctly(): void
    {
        $user = User::factory()->create();
        $data = [
            'customer_id' => $user->id,
            'status' => OrderStatus::PENDING->value,
            'line_items' => [
                ['title' => 'Product A', 'price' => 100, 'quantity' => 2, 'taxable' => true],
            ],
            'tax_lines' => [
                ['label' => 'VAT', 'rate' => 20, 'type' => 'normal'],
            ],
            'discount' => [
                'type' => 'fixed_amount',
                'value' => 50,
            ],
        ];

        // Calculation:
        // Subtotal: 200
        // Discount: 50
        // Taxable Base: 150
        // Tax: 20% of 150 = 30
        // Grand Total: 150 + 30 = 180

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.store'), $data)
            ->assertRedirect();

        $order = (Foundry::$orderModel)::where('customer_id', $user->id)->latest()->first();
        $order->load(['tax_lines', 'line_items']);

        $this->assertEquals(200.00, (float) $order->sub_total);
        $this->assertEquals(50.00, (float) $order->discount_total);
        $this->assertEquals(30.00, (float) $order->tax_total);
        $this->assertEquals(180.00, (float) $order->grand_total);

        $this->assertCount(1, $order->tax_lines);
        $this->assertEquals(30.00, (float) $order->tax_lines->first()->amount);
        $this->assertEquals(30.00, (float) TaxLine::where('taxable_id', $order->id)->first()->amount);
    }
}
