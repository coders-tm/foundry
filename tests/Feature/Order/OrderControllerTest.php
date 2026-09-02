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
    expect($order->number)->not->toBeNull();
    $this->assertDatabaseHas('line_items', ['title' => 'Product 1', 'itemable_id' => $order->id, 'itemable_type' => 'Order']);
});

it('store marks as paid if payment method provided', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'payment_method' => 'manual', 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]]];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertRedirect();
    $order = Order::where('customer_id', $user->id)->first();
    expect($order->is_paid)->toBeTrue();
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
    expect($item->refresh()->status->value)->toBe($data['status']);
    $this->assertDatabaseHas('line_items', ['itemable_id' => $item->id, 'itemable_type' => 'Order', 'title' => 'Updated Product']);
});

it('cancel marks order as cancelled', function () {
    $item = Order::factory()->create(['status' => OrderStatus::PENDING]);
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.cancel', $item))->assertRedirect();
    expect($item->refresh()->status)->toBe(OrderStatus::CANCELLED);
});

it('mark as paid updates payment status', function () {
    $item = Order::factory()->create(['payment_status' => PaymentStatus::PAYMENT_PENDING]);
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.mark-as-paid', $item))->assertRedirect();
    expect($item->refresh()->is_paid)->toBeTrue();
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
    expect($item->refresh()->status)->toBe(OrderStatus::REFUNDED);
});

it('show subscription invoice includes line items', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => 19.99, 'trial_days' => 0]);
    $this->actingAs($this->admin, 'admin')->post(route('admin.subscriptions.store'), ['user_id' => $user->id, 'plan' => $plan->id, 'starts_at' => now()->toDateTimeString(), 'generate_invoice' => true, 'mark_as_paid' => true, 'payment_method' => 'manual'])->assertSuccessful();
    $subscription = Foundry::$subscriptionModel::where('user_id', $user->id)->first();
    expect($subscription)->not->toBeNull();
    $subscription->refresh();
    $invoice = $subscription->latestInvoice;
    expect($invoice)->not->toBeNull();
    expect($invoice->orderable_type)->toBe('Subscription');
    $this->assertDatabaseHas('line_items', ['itemable_id' => $invoice->id, 'itemable_type' => 'Order']);
    $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.orders.show', $invoice->id))->assertSuccessful();
    $response->assertJsonStructure(['id', 'line_items' => ['*' => ['title', 'price']]]);
    $data = $response->json();
    expect($data['line_items'])->toHaveCount(1);
    $lineItem = $data['line_items'][0];
    expect($lineItem['price'])->toEqual(19.99);
    expect($lineItem['title'])->toBeString();
});

it('store with fixed discount creates discount line', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]], 'discount' => ['type' => 'fixed_amount', 'value' => 10, 'description' => 'Test discount'], 'sub_total' => 100, 'discount_total' => 10, 'grand_total' => 90];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $order = (Foundry::$orderModel)::where('customer_id', $user->id)->first();
    expect($order)->not->toBeNull();
    expect($order->discount_total)->toEqual(10);
    $this->assertDatabaseHas('discount_lines', ['discountable_id' => $order->id, 'discountable_type' => 'Order', 'type' => 'fixed_amount', 'value' => 10, 'description' => 'Test discount']);
    $order->refresh();
    expect($order->discount)->not->toBeNull();
    expect($order->discount->type)->toBe('fixed_amount');
    expect($order->discount->value)->toEqual(10);
    expect($order->discount->description)->toBe('Test discount');
});

it('store with percentage discount creates discount line', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]], 'discount' => ['type' => 'percentage', 'value' => 10, 'description' => '10% off'], 'sub_total' => 100, 'discount_total' => 10, 'grand_total' => 90];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $order = (Foundry::$orderModel)::where('customer_id', $user->id)->first();
    expect($order)->not->toBeNull();
    expect($order->discount_total)->toEqual(10);
    $this->assertDatabaseHas('discount_lines', ['discountable_id' => $order->id, 'discountable_type' => 'Order', 'type' => 'percentage', 'value' => 10, 'description' => '10% off']);
    $order->refresh();
    expect($order->discount)->not->toBeNull();
    expect($order->discount->type)->toBe('percentage');
    expect($order->discount->value)->toEqual(10);
});

it('update with discount creates or updates discount line', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'discount_total' => 0, 'sub_total' => 100, 'tax_total' => 0, 'grand_total' => 100]);
    $order->line_items()->create(['title' => 'Product 1', 'price' => 100, 'quantity' => 1]);
    $data = ['status' => 'pending', 'sub_total' => 100, 'tax_total' => 0, 'discount_total' => 15, 'grand_total' => 85, 'line_items' => [['title' => 'Product 1', 'price' => 100, 'quantity' => 1]], 'discount' => ['type' => 'fixed_amount', 'value' => 15, 'description' => 'Updated discount']];
    $this->actingAs($this->admin, 'admin')->patch(route('admin.orders.update', $order), $data)->assertSessionHasNoErrors()->assertRedirect();
    $order->refresh();
    expect($order->discount_total)->toEqual(15);
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
    expect((float) $order->sub_total)->toEqual(200.00);
    expect((float) $order->discount_total)->toEqual(20.00);
    expect((float) $order->tax_total)->toEqual(27.90);
    expect((float) $order->grand_total)->toEqual(207.90);
    expect($order->tax_lines)->toHaveCount(2);
    expect((float) $order->tax_lines->where('label', 'GST')->first()->amount)->toEqual(18.00);
    expect((float) $order->tax_lines->where('label', 'Cess')->first()->amount)->toEqual(9.90);
});

it('order update recalculates correctly when data changes', function () {
    $user = User::factory()->create();
    $order = Order::create(['customer_id' => $user->id, 'line_items' => [['title' => 'Initial Item', 'price' => 100, 'quantity' => 1]], 'collect_tax' => false]);
    expect((float) $order->grand_total)->toEqual(100.00);
    $order->update(['line_items' => [['title' => 'New Item', 'price' => 200, 'quantity' => 1, 'taxable' => true]], 'tax_lines' => [['label' => 'Updated Tax', 'rate' => 10]]]);
    $order->refresh();
    $order->load(['tax_lines', 'line_items']);
    expect((float) $order->sub_total)->toEqual(200.00);
    expect((float) $order->tax_total)->toEqual(20.00);
    expect((float) $order->grand_total)->toEqual(220.00);
    expect($order->line_items)->toHaveCount(1);
    expect($order->line_items->first()->title)->toBe('New Item');
    expect($order->tax_lines)->toHaveCount(1);
    expect((float) $order->tax_lines->first()->amount)->toEqual(20.00);
});

it('store endpoint calculates correctly', function () {
    $user = User::factory()->create();
    $data = ['customer_id' => $user->id, 'status' => OrderStatus::PENDING->value, 'line_items' => [['title' => 'Product A', 'price' => 100, 'quantity' => 2, 'taxable' => true]], 'tax_lines' => [['label' => 'VAT', 'rate' => 20, 'type' => 'normal']], 'discount' => ['type' => 'fixed_amount', 'value' => 50]];
    $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), $data)->assertRedirect();
    $order = (Foundry::$orderModel)::where('customer_id', $user->id)->latest()->first();
    $order->load(['tax_lines', 'line_items']);
    expect((float) $order->sub_total)->toEqual(200.00);
    expect((float) $order->discount_total)->toEqual(50.00);
    expect((float) $order->tax_total)->toEqual(30.00);
    expect((float) $order->grand_total)->toEqual(180.00);
    expect($order->tax_lines)->toHaveCount(1);
    expect((float) $order->tax_lines->first()->amount)->toEqual(30.00);
    expect((float) TaxLine::where('taxable_id', $order->id)->first()->amount)->toEqual(30.00);
});
