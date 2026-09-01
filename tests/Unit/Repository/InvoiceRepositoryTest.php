<?php

uses(Foundry\Tests\BaseTestCase::class)->use(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('calculates simple taxable scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(100, $repository->sub_total);
    $this->assertEquals(10, $repository->tax_total);
    $this->assertEquals(110, $repository->grand_total);
});

it('calculates mixed taxability scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(200, $repository->sub_total);
    $this->assertEquals(10, $repository->tax_total);
    $this->assertEquals(210, $repository->grand_total);
});

it('calculates line item discount scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
        'collect_tax' => true,
        'line_items' => [
            [
                'title' => 'Discounted Item',
                'price' => 100,
                'quantity' => 1,
                'taxable' => true,
                'discount' => new \Foundry\Models\Order\DiscountLine([
                    'type' => 'fixed_amount',
                    'value' => 20,
                ]),
            ],
        ],
        'tax_lines' => [
            ['label' => 'VAT', 'rate' => 10],
        ],
    ]);

    $this->assertEquals(100, $repository->sub_total);
    $this->assertEquals(20, $repository->line_discount_total);
    $this->assertEquals(0, $repository->order_discount_total);
    $this->assertEquals(20, $repository->discount_total);
    $this->assertEquals(8, $repository->tax_total);
    $this->assertEquals(88, $repository->grand_total);
});

it('calculates order level fixed discount scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(100, $repository->sub_total);
    $this->assertEquals(20, $repository->order_discount_total);
    $this->assertEquals(8, $repository->tax_total);
    $this->assertEquals(88, $repository->grand_total);
});

it('calculates mixed taxability with order discount prorating scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(200, $repository->sub_total);
    $this->assertEquals(20, $repository->order_discount_total);
    $this->assertEquals(10, $repository->taxable_discount);
    $this->assertEquals(9, $repository->tax_total);
    $this->assertEquals(189, $repository->grand_total);
});

it('calculates compounding taxes scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(10, $repository->default_tax_total);
    $this->assertEquals(15.5, $repository->tax_total);
    $this->assertEquals(115.5, $repository->grand_total);
});

it('calculates quantities and rounding scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(110.49, $repository->sub_total);
    $this->assertEquals(10.52, $repository->tax_total);
    $this->assertEquals(115.76, $repository->grand_total);
});

it('calculates combined discounts scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(100, $repository->sub_total);
    $this->assertEquals(20, $repository->line_discount_total);
    $this->assertEquals(10, $repository->order_discount_total);
    $this->assertEquals(30, $repository->discount_total);
    $this->assertEquals(7, $repository->tax_total);
    $this->assertEquals(77, $repository->grand_total);
});

it('calculates concurrent taxes indian gst scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(100, $repository->sub_total);
    $this->assertEquals(18, $repository->tax_total);
    $this->assertEquals(118, $repository->grand_total);
    $this->assertCount(2, $repository->tax_lines);
    $this->assertEquals(9, $repository->tax_lines[0]['amount']);
    $this->assertEquals(9, $repository->tax_lines[1]['amount']);
});

it('calculates compounded taxes with discount scenario', function () {
    $repository = new \Foundry\Repository\InvoiceRepository([
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

    $this->assertEquals(100, $repository->sub_total);
    $this->assertEquals(20, $repository->discount_total);
    $this->assertEquals(8, $repository->default_tax_total);
    $this->assertEquals(12.4, $repository->tax_total);
    $this->assertEquals(92.4, $repository->grand_total);
});
