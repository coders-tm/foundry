<?php

use Foundry\Enum\OrderStatus;
use Foundry\Enum\PaymentStatus;
use Foundry\Foundry;
use Foundry\Models\Admin;
use Foundry\Models\Notification;
use Foundry\Models\Order;
use Foundry\Models\Order\Customer;
use Foundry\Models\Order\TaxLine;
use Foundry\Models\Payment;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Notifications\OrderInvoiceNotification;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(FeatureTestCase::class)
    ->use(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create(['is_super_admin' => true]);
    $this->user = User::factory()->create();
});

it('requires authentication for index', function () {
    $this->get(route('admin.orders.index'))->assertRedirect();
});

it('can access index page', function () {
    $this->actingAs($this->admin, 'admin')->get(route('admin.orders.index'))->assertSuccessful();
});

it('store creates item and redirects', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'note' => 'Test order note', 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]], 'sub_total' => 100, 'grand_total' => 100];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseHas('orders', ['customer_id' => $data['customer_id'], 'status' => $data['status']]);
    $order = Order::where('customer_id', $user->id)->first();
    $this->assertNotNull($order->number);
    $this->assertDatabaseHas('line_items', ['title' => 'Product 1', 'itemable_id' => $order->id, 'itemable_type' => 'Order']);
});

it('store marks as paid if payment method provided', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'payment_method' => 'manual', 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]]];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertRedirect();
    $order = Order::where('customer_id', $user->id)->first();
    $this->assertTrue($order->is_paid);
});

it('store sends invoice if invoice data provided', function () {
    Illuminate\Support\Facades\Notification::fake();
    Notification::factory()->create(['type' => 'user:invoice-sent', 'subject' => 'Invoice for Order {{ order.number }}', 'content' => 'Hello']);
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'invoice_data' => ['to' => 'custom@example.com', 'subject' => 'Custom Subject', 'message' => 'Custom Message'], 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]]];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertRedirect();
    $order = Order::where('customer_id', $user->id)->first();
    Illuminate\Support\Facades\Notification::assertSentTo(new Customer(['id' => $order->customer_id]), OrderInvoiceNotification::class, fn ($n, $c, $notifiable) => $notifiable->email === 'custom@example.com');
    $this->assertDatabaseHas('logs', ['type' => 'invoice_sent', 'logable_id' => $order->id, 'logable_type' => 'Order']);
});

it('update modifies item', function () {
    $item = Order::factory()->create(['status' => OrderStatus::PENDING]);
    $data = ['status' => OrderStatus::COMPLETED->value, 'line_items' => [['title' => 'Updated Product', 'price' => 150, 'quantity' => 1]]];
    $this->actingAs($this->admin, 'admin')->patch(route('admin.orders.update', $item), $data)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertEquals($data['status'], $item->refresh()->status->value);
    $this->assertDatabaseHas('line_items', ['itemable_id' => $item->id, 'itemable_type' => 'Order', 'title' => 'Updated Product']);
});

it('cancel marks order as cancelled', function () {
    $item = Order::factory()->create(['status' => OrderStatus::PENDING]);
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.cancel', $item))->assertRedirect();
    $this->assertEquals(OrderStatus::CANCELLED, $item->refresh()->status);
});

it('mark as paid updates payment status', function () {
    $item = Order::factory()->create(['payment_status' => PaymentStatus::PAYMENT_PENDING]);
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.mark-as-paid', $item))->assertRedirect();
    $this->assertTrue($item->refresh()->is_paid);
});

it('send invoice sends notification', function () {
    Illuminate\Support\Facades\Notification::fake();
    $user = User::factory()->create(['email' => 'original@example.com']);
    $item = Order::factory()->create(['customer_id' => $user->id]);
    Notification::factory()->create(['type' => 'user:invoice-sent', 'subject' => 'Invoice for Order {{ order.number }}', 'content' => 'Hello']);
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.send-invoice', $item))->assertRedirect();
    Illuminate\Support\Facades\Notification::assertSentTo(new Customer(['id' => $item->customer_id]), OrderInvoiceNotification::class);
    $customEmail = 'custom@example.com';
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.send-invoice', $item), ['to' => $customEmail])->assertRedirect();
    Illuminate\Support\Facades\Notification::assertSentTo(new Customer(['id' => $item->customer_id]), OrderInvoiceNotification::class, fn ($n, $c, $notifiable) => $notifiable->email === $customEmail);
});

it('download invoice returns streamed response', function () {
    $user = User::factory()->create();
    $item = Order::factory()->create(['customer_id' => $user->id]);
    $this->actingAs($this->admin, 'admin')->get(route('admin.orders.download-invoice', $item))->assertSuccessful()->assertHeader('Content-Type', 'application/pdf');
});

it('refund processes refund', function () {
    $user = User::factory()->create();
    $item = Order::factory()->create(['customer_id' => $user->id, 'payment_status' => PaymentStatus::PAID, 'status' => OrderStatus::COMPLETED, 'grand_total' => 100, 'paid_total' => 100]);
    $item->payments()->create(['transaction_id' => 'txn_123', 'amount' => 100, 'status' => Payment::STATUS_COMPLETED, 'processed_at' => now(), 'currency' => 'USD', 'provider' => 'manual']);
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.refund', $item), ['to_wallet' => true])->assertRedirect();
    $this->assertEquals(OrderStatus::REFUNDED, $item->refresh()->status);
});

it('show subscription invoice includes line items', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => 19.99, 'trial_days' => 0]);
    $this->actingAs($this->admin, 'admin')->post(route('admin.subscriptions.store'), ['user_id' => $user->id, 'plan' => $plan->id, 'starts_at' => now()->toDateTimeString(), 'generate_invoice' => true, 'mark_as_paid' => true, 'payment_method' => 'manual'])->assertSuccessful();
    $subscription = Foundry::$subscriptionModel::where('user_id', $user->id)->first();
    $this->assertNotNull($subscription);
    $subscription->refresh();
    $invoice = $subscription->latestInvoice;
    $this->assertNotNull($invoice);
    $this->assertEquals('Subscription', $invoice->orderable_type);
    $this->assertDatabaseHas('line_items', ['itemable_id' => $invoice->id, 'itemable_type' => 'Order']);
    $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.orders.show', $invoice->id))->assertSuccessful();
    $response->assertJsonStructure(['id', 'line_items' => ['*' => ['title', 'price']]]);
    $data = $response->json();
    $this->assertCount(1, $data['line_items']);
    $lineItem = $data['line_items'][0];
    $this->assertEquals(19.99, $lineItem['price']);
    $this->assertIsString($lineItem['title']);
});

it('store with fixed discount creates discount line', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]], 'discount' => ['type' => 'fixed_amount', 'value' => 10, 'description' => 'Test discount'], 'sub_total' => 100, 'discount_total' => 10, 'grand_total' => 90];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $order = (Foundry::$orderModel)::where('customer_id', $user->id)->first();
    $this->assertNotNull($order);
    $this->assertEquals(10, $order->discount_total);
    $this->assertDatabaseHas('discount_lines', ['discountable_id' => $order->id, 'discountable_type' => 'Order', 'type' => 'fixed_amount', 'value' => 10, 'description' => 'Test discount']);
    $order->refresh();
    $this->assertNotNull($order->discount);
    $this->assertEquals('fixed_amount', $order->discount->type);
    $this->assertEquals(10, $order->discount->value);
    $this->assertEquals('Test discount', $order->discount->description);
});

it('store with percentage discount creates discount line', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]], 'discount' => ['type' => 'percentage', 'value' => 10, 'description' => '10% off'], 'sub_total' => 100, 'discount_total' => 10, 'grand_total' => 90];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $order = (Foundry::$orderModel)::where('customer_id', $user->id)->first();
    $this->assertNotNull($order);
    $this->assertEquals(10, $order->discount_total);
    $this->assertDatabaseHas('discount_lines', ['discountable_id' => $order->id, 'discountable_type' => 'Order', 'type' => 'percentage', 'value' => 10, 'description' => '10% off']);
    $order->refresh();
    $this->assertNotNull($order->discount);
    $this->assertEquals('percentage', $order->discount->type);
    $this->assertEquals(10, $order->discount->value);
});

it('update with discount creates or updates discount line', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'discount_total' => 0, 'sub_total' => 100, 'tax_total' => 0, 'grand_total' => 100]);
    $order->line_items()->create(['title' => 'Product 1', 'price' => 100, 'quantity' => 1]);
    $data = ['status' => 'pending', 'sub_total' => 100, 'tax_total' => 0, 'discount_total' => 15, 'grand_total' => 85, 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]], 'discount' => ['type' => 'fixed_amount', 'value' => 15, 'description' => 'Updated discount']];
    $this->actingAs($this->admin, 'admin')->patch(route('admin.orders.update', $order), $data)->assertSessionHasNoErrors()->assertRedirect();
    $order->refresh();
    $this->assertEquals(15, $order->discount_total);
    $this->assertDatabaseHas('discount_lines', ['discountable_id' => $order->id, 'discountable_type' => 'Order', 'type' => 'fixed_amount', 'value' => 15, 'description' => 'Updated discount']);
});

it('show order includes all necessary relationships', function () {
    $user = User::factory()->create();
    $order = Order::create(['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1, 'taxable' => true]], 'discount' => ['type' => 'fixed_amount', 'value' => 20, 'description' => 'Test discount'], 'tax_lines' => [['label' => 'Sales Tax', 'rate' => 10]], 'contact' => ['email' => 'test@example.com', 'phone_number' => '1234567890']]);
    $this->actingAs($this->admin, 'admin')->getJson(route('admin.orders.show', $order))->assertSuccessful()->assertJsonStructure(['id', 'line_items', 'tax_lines', 'discount', 'contact'])->assertJsonPath('discount.value', '20.00')->assertJsonPath('tax_lines.0.label', 'Sales Tax')->assertJsonPath('tax_lines.0.amount', '8.00');
});

it('order create calculates correctly with compounded taxes and discount', function () {
    $user = User::factory()->create();
    $order = Order::create(['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1, 'taxable' => true], ['title' => 'Product 2', 'price' => 100, 'quantity' => 1, 'taxable' => true]], 'discount' => ['type' => 'percentage', 'value' => 10], 'tax_lines' => [['label' => 'GST', 'rate' => 10, 'type' => 'normal'], ['label' => 'Cess', 'rate' => 5, 'type' => 'compounded']]]);
    $this->assertEquals(200.00, (float) $order->sub_total);
    $this->assertEquals(20.00, (float) $order->discount_total);
    $this->assertEquals(27.90, (float) $order->tax_total);
    $this->assertEquals(207.90, (float) $order->grand_total);
    $this->assertCount(2, $order->tax_lines);
    $this->assertEquals(18.00, (float) $order->tax_lines->where('label', 'GST')->first()->amount);
    $this->assertEquals(9.90, (float) $order->tax_lines->where('label', 'Cess')->first()->amount);
});

it('order update recalculates correctly when data changes', function () {
    $user = User::factory()->create();
    $order = Order::create(['customer_id' => $user->id, 'line_items' => [['title' => 'Initial Item', 'price' => 100, 'quantity' => 1]], 'collect_tax' => false]);
    $this->assertEquals(100.00, (float) $order->grand_total);
    $order->update(['line_items' => [['title' => 'New Item', 'price' => 200, 'quantity' => 1, 'taxable' => true]], 'tax_lines' => [['label' => 'Updated Tax', 'rate' => 10]]]);
    $order->refresh();
    $order->load(['tax_lines', 'line_items']);
    $this->assertEquals(200.00, (float) $order->sub_total);
    $this->assertEquals(20.00, (float) $order->tax_total);
    $this->assertEquals(220.00, (float) $order->grand_total);
    $this->assertCount(1, $order->line_items);
    $this->assertEquals('New Item', $order->line_items->first()->title);
    $this->assertCount(1, $order->tax_lines);
    $this->assertEquals(20.00, (float) $order->tax_lines->first()->amount);
});

it('store endpoint calculates correctly', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'line_items' => [['title' => 'Product A', 'price' => 100, 'quantity' => 2, 'taxable' => true]], 'tax_lines' => [['label' => 'VAT', 'rate' => 20, 'type' => 'normal']], 'discount' => ['type' => 'fixed_amount', 'value' => 50]];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertRedirect();
    $order = (Foundry::$orderModel)::where('customer_id', $user->id)->latest()->first();
    $order->load(['tax_lines', 'line_items']);
    $this->assertEquals(200.00, (float) $order->sub_total);
    $this->assertEquals(50.00, (float) $order->discount_total);
    $this->assertEquals(30.00, (float) $order->tax_total);
    $this->assertEquals(180.00, (float) $order->grand_total);
    $this->assertCount(1, $order->tax_lines);
    $this->assertEquals(30.00, (float) $order->tax_lines->first()->amount);
    $this->assertEquals(30.00, (float) TaxLine::where('taxable_id', $order->id)->first()->amount);
});
