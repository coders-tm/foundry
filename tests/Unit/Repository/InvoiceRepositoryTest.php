<?php

use Foundry\Models\Order\DiscountLine;
use Foundry\Repository\InvoiceRepository;
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(BaseTestCase::class)->use(RefreshDatabase::class);

it('calculates simple taxable scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Taxable Item',
                'price' => 100,
                'total' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    expect($repository->sub_total)->toEqual(100);
    expect($repository->tax_total)->toEqual(10);
    expect($repository->grand_total)->toEqual(110);
});

it('calculates mixed taxability scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Taxable Item',
                'price' => 100,
                'total' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
            [
                'title' => 'Non-Taxable Item',
                'price' => 100,
                'total' => 100,
                'quantity' => 1,
                'taxable' => false,
            ],
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    expect($repository->sub_total)->toEqual(200);
    expect($repository->tax_total)->toEqual(10);
    expect($repository->grand_total)->toEqual(210);
});

it('calculates line item discount scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Discounted Item',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
                'discount' => new DiscountLine([
                    'type' => 'fixed_amount',
                    'value' => 20,
                ]),
            ],
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    expect($repository->sub_total)->toEqual(100);
    expect($repository->line_discount_total)->toEqual(20);
    expect($repository->order_discount_total)->toEqual(0);
    expect($repository->discount_total)->toEqual(20);
    expect($repository->tax_total)->toEqual(8);
    expect($repository->grand_total)->toEqual(88);
});

it('calculates order level fixed discount scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Item',
                'price' => 100,
                'total' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
        ],
        'discount' => [
            'description' => 'Coupon',
            'value' => 20,
            'type' => 'fixed_amount',
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    expect($repository->sub_total)->toEqual(100);
    expect($repository->order_discount_total)->toEqual(20);
    expect($repository->tax_total)->toEqual(8);
    expect($repository->grand_total)->toEqual(88);
});

it('calculates mixed taxability with order discount prorating scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Taxable Item',
                'price' => 100,
                'total' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
            [
                'title' => 'Non-Taxable Item',
                'price' => 100,
                'total' => 100,
                'quantity' => 1,
                'taxable' => false,
            ],
        ],
        'discount' => [
            'description' => 'Coupon',
            'value' => 20,
            'type' => 'fixed_amount',
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    expect($repository->sub_total)->toEqual(200);
    expect($repository->order_discount_total)->toEqual(20);
    expect($repository->taxable_discount)->toEqual(10);
    expect($repository->tax_total)->toEqual(9);
    expect($repository->grand_total)->toEqual(189);
});

it('calculates compounding taxes scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Item',
                'price' => 100,
                'total' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10, 'type' => 'default'],
            ['label' => 'Compound Tax', 'rate' => 5, 'type' => 'compounded'],
        ],
    ]);

    expect($repository->default_tax_total)->toEqual(10);
    expect($repository->tax_total)->toEqual(15.5);
    expect($repository->grand_total)->toEqual(115.5);
});

it('calculates quantities and rounding scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Item 1',
                'price' => 33.33,
                'quantity' => 3,
                'taxable' => true,
            ],
            [
                'title' => 'Item 2',
                'price' => 10.50,
                'quantity' => 1,
                'taxable' => true,
                'discount' => [
                    'type' => 'fixed_amount',
                    'value' => 5.25,
                ],
            ],
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    expect($repository->sub_total)->toEqual(110.49);
    expect($repository->tax_total)->toEqual(10.52);
    expect($repository->grand_total)->toEqual(115.76);
});

it('calculates combined discounts scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Mixed Discount Item',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
                'discount' => [
                    'type' => 'fixed_amount',
                    'value' => 20,
                ],
            ],
        ],
        'discount' => [
            'description' => 'Fixed Order Discount',
            'value' => 10.00,
            'type' => 'fixed_amount',
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    expect($repository->sub_total)->toEqual(100);
    expect($repository->line_discount_total)->toEqual(20);
    expect($repository->order_discount_total)->toEqual(10);
    expect($repository->discount_total)->toEqual(30);
    expect($repository->tax_total)->toEqual(7);
    expect($repository->grand_total)->toEqual(77);
});

it('calculates concurrent taxes indian gst scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Item',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
            ],
        ],
        'tax_lines' => [
            ['label' => 'CGST', 'rate' => 9],
            ['label' => 'SGST', 'rate' => 9],
        ],
    ]);

    expect($repository->sub_total)->toEqual(100);
    expect($repository->tax_total)->toEqual(18);
    expect($repository->grand_total)->toEqual(118);
    expect($repository->tax_lines)->toHaveCount(2);
    expect($repository->tax_lines[0]['amount'])->toEqual(9);
    expect($repository->tax_lines[1]['amount'])->toEqual(9);
});

it('calculates compounded taxes with discount scenario', function () {
    $repository = new InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Item',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
                'discount' => [
                    'type' => 'percentage',
                    'value' => 20,
                ],
            ],
        ],
        'tax_lines' => [
            ['label' => 'Normal Tax', 'rate' => 10, 'type' => 'default'],
            ['label' => 'Compound Tax', 'rate' => 5, 'type' => 'compounded'],
        ],
    ]);

    expect($repository->sub_total)->toEqual(100);
    expect($repository->discount_total)->toEqual(20);
    expect($repository->default_tax_total)->toEqual(8);
    expect($repository->tax_total)->toEqual(12.4);
    expect($repository->grand_total)->toEqual(92.4);
});
