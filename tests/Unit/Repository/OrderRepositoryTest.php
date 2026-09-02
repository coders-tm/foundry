<?php

use Foundry\Models\Order;
use Foundry\Models\Tax;
use Foundry\Repository\OrderRepository;
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(BaseTestCase::class)->use(RefreshDatabase::class);

it('calculates simple taxable from request', function () {
    $order = new Order;
    $request = Request::create('/orders', 'POST', [
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Taxable Item',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
        'billing_address' => [
            'country_code' => 'US',
            'state_code' => 'NY',
        ],
    ]);

    $order = OrderRepository::fromRequest($request, $order);

    expect($order->sub_total)->toEqual(100);
    expect($order->tax_total)->toEqual(10);
    expect($order->grand_total)->toEqual(110);
    expect($order->line_items)->toHaveCount(1);
    expect($order->line_items->first()->title)->toBe('Taxable Item');
});

it('calculates line item discount from request', function () {
    $order = new Order;
    $request = Request::create('/orders', 'POST', [
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Discounted Item',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
                'discount' => [
                    'type' => 'fixed_amount',
                    'value' => 20,
                ],
            ],
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    $order = OrderRepository::fromRequest($request, $order);

    expect($order->sub_total)->toEqual(100);
    expect($order->discount_total)->toEqual(20);
    expect($order->tax_total)->toEqual(8);
    expect($order->grand_total)->toEqual(88);

    $lineItem = $order->line_items->first();
    expect($lineItem->discount)->not->toBeNull();
    expect($lineItem->discount->value)->toEqual(20);
});

it('calculates order level discount from request', function () {
    $order = new Order;
    $request = Request::create('/orders', 'POST', [
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Item',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
        ],
        'discount' => [
            'type' => 'fixed_amount',
            'value' => 20,
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    $order = OrderRepository::fromRequest($request, $order);

    expect($order->sub_total)->toEqual(100);
    expect($order->discount_total)->toEqual(20);
    expect($order->tax_total)->toEqual(8);
    expect($order->grand_total)->toEqual(88);
    expect($order->discount)->not->toBeNull();
    expect($order->discount->value)->toEqual(20);
});

it('hydrates customer and address from request', function () {
    $order = new Order;
    $request = Request::create('/orders', 'POST', [
        'customer' => [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'address' => [
                'line1' => '123 Main St',
                'city' => 'New York',
                'country_code' => 'US',
            ],
        ],
        'line_items' => [],
    ]);

    $order = OrderRepository::fromRequest($request, $order);

    expect($order->customer)->not->toBeNull();
    expect($order->customer->first_name)->toBe('John');
    expect($order->customer->address)->not->toBeNull();
    expect($order->customer->address->line1)->toBe('123 Main St');
});

it('hydrates contact from request', function () {
    $order = new Order;
    $request = Request::create('/orders', 'POST', [
        'contact' => [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
        ],
        'line_items' => [],
    ]);

    $order = OrderRepository::fromRequest($request, $order);

    expect($order->contact)->not->toBeNull();
    expect($order->contact->first_name)->toBe('Jane');
    expect($order->contact->email)->toBe('jane@example.com');
});

it('calculates concurrent taxes indian gst from request', function () {
    Tax::create([
        'country' => 'India',
        'code' => 'IN',
        'state' => '*',
        'label' => 'CGST',
        'rate' => 9,
        'priority' => 1,
    ]);
    Tax::create([
        'country' => 'India',
        'code' => 'IN',
        'state' => '*',
        'label' => 'SGST',
        'rate' => 9,
        'priority' => 2,
    ]);

    $order = new Order;
    $request = Request::create('/orders', 'POST', [
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Product',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
        ],
        'billing_address' => [
            'country' => 'India',
        ],
    ]);

    $order = OrderRepository::fromRequest($request, $order);

    expect($order->sub_total)->toEqual(100);
    expect($order->tax_total)->toEqual(18);
    expect($order->grand_total)->toEqual(118);
    expect($order->tax_lines)->toHaveCount(2);
    expect($order->tax_lines[0]['label'])->toBe('CGST');
    expect($order->tax_lines[1]['label'])->toBe('SGST');
});

it('calculates compounding taxes from request', function () {
    Tax::create([
        'country' => 'Canada',
        'code' => 'CA',
        'state' => '*',
        'label' => 'GST',
        'rate' => 5,
        'priority' => 1,
    ]);
    Tax::create([
        'country' => 'Canada',
        'code' => 'CA',
        'state' => '*',
        'label' => 'PST (Compounded)',
        'rate' => 10,
        'compounded' => true,
        'priority' => 2,
    ]);

    $order = new Order;
    $request = Request::create('/orders', 'POST', [
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Product',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
        ],
        'billing_address' => [
            'country' => 'Canada',
        ],
    ]);

    $order = OrderRepository::fromRequest($request, $order);

    expect($order->tax_total)->toEqual(15.5);
    expect($order->grand_total)->toEqual(115.5);
});
