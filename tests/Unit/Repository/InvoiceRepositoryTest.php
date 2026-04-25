<?php

namespace Foundry\Tests\Unit\Repository;

use Foundry\Models\Order\DiscountLine;
use Foundry\Repository\InvoiceRepository;
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceRepositoryTest extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Scenario: Simple Taxable
     * 1 Taxable Item ($100), 10% Tax.
     */
    public function test_simple_taxable_scenario()
    {
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

        $this->assertEquals(100, $repository->sub_total);
        $this->assertEquals(10, $repository->tax_total);
        $this->assertEquals(110, $repository->grand_total);
    }

    /**
     * Scenario: Mixed Taxability
     * 1 Taxable Item ($100), 1 Non-Taxable ($100), 10% Tax.
     */
    public function test_mixed_taxability_scenario()
    {
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

        $this->assertEquals(200, $repository->sub_total);
        $this->assertEquals(10, $repository->tax_total); // Tax only on the $100 taxable item
        $this->assertEquals(210, $repository->grand_total);
    }

    /**
     * Scenario: Line Item Discounts
     * 1 Item ($100) with $20 line discount, 10% Tax.
     */
    public function test_line_item_discount_scenario()
    {
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

        $this->assertEquals(100, $repository->sub_total);
        $this->assertEquals(20, $repository->line_discount_total);
        $this->assertEquals(0, $repository->order_discount_total);
        $this->assertEquals(20, $repository->discount_total);
        $this->assertEquals(8, $repository->tax_total); // 10% of $80
        $this->assertEquals(88, $repository->grand_total); // 80 + 8
    }

    /**
     * Scenario: Order Level Discount (Fixed)
     * 1 Item ($100), $20 Order Discount, 10% Tax.
     */
    public function test_order_level_fixed_discount_scenario()
    {
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

        $this->assertEquals(100, $repository->sub_total);
        $this->assertEquals(20, $repository->order_discount_total);
        $this->assertEquals(8, $repository->tax_total); // 10% of (100 - 20)
        $this->assertEquals(88, $repository->grand_total); // (100 - 20) + 8
    }

    /**
     * Scenario: Mixed Taxability + Order Discount (Pro-rating)
     * 1 Taxable ($100), 1 Non-Taxable ($100), $20 Order Discount, 10% Tax.
     */
    public function test_mixed_taxability_with_order_discount_prorating_scenario()
    {
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

        // Total order discount is $20. Total items = 2.
        // Discount per item = 20 / 2 = 10.
        // Taxable discount = 10 * 1 (taxable item) = 10.
        // Taxable base = 100 - 10 = 90.
        // Tax = 90 * 10% = 9.
        // Grand total = (200 - 20) + 9 = 189.

        $this->assertEquals(200, $repository->sub_total);
        $this->assertEquals(20, $repository->order_discount_total);
        $this->assertEquals(10, $repository->taxable_discount);
        $this->assertEquals(9, $repository->tax_total);
        $this->assertEquals(189, $repository->grand_total);
    }

    /**
     * Scenario: Compounding Taxes
     * 1 Item ($100), Tax 1 (10%), Tax 2 (5% compounded).
     */
    public function test_compounding_taxes_scenario()
    {
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

        // Tax 1 = 100 * 10% = 10.
        // Tax 2 = (100 + 10) * 5% = 110 * 5% = 5.5.
        // Total Tax = 15.5.

        $this->assertEquals(10, $repository->default_tax_total);
        $this->assertEquals(15.5, $repository->tax_total);
        $this->assertEquals(115.5, $repository->grand_total);
    }

    /**
     * Scenario: Quantities and Rounding
     * Mixed quantities and specific decimals to verify rounding stability.
     */
    public function test_quantities_and_rounding_scenario()
    {
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

        // Gross sub_total = (33.33 * 3) + 10.50 = 99.99 + 10.50 = 110.49.
        // Net taxable base = (99.99) + (10.50 - 5.25) = 99.99 + 5.25 = 105.24.
        // Tax = 105.24 * 10% = 10.524 -> 10.52.
        // Grand Total = 105.24 + 10.52 = 115.76.

        $this->assertEquals(110.49, $repository->sub_total);
        $this->assertEquals(10.52, $repository->tax_total);
        $this->assertEquals(115.76, $repository->grand_total);
    }

    /**
     * Scenario: Both Line-item and Order-level discounts
     */
    public function test_combined_discounts_scenario()
    {
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

        // sub_total (gross) = 100.
        // line_discount_total = 20.
        // order_discount_total = 10.
        // discount_total = 30.
        // grand_total = (100 - 30) + tax.
        // taxable_base = 100 - 30 = 70.
        // tax = 7.
        // grand_total = 70 + 7 = 77.

        $this->assertEquals(100, $repository->sub_total);
        $this->assertEquals(20, $repository->line_discount_total);
        $this->assertEquals(10, $repository->order_discount_total);
        $this->assertEquals(30, $repository->discount_total); // Total savings = 30
        $this->assertEquals(7, $repository->tax_total);
        $this->assertEquals(77, $repository->grand_total);
    }

    /**
     * Scenario: Concurrent Taxes (Indian GST)
     * 1 Item ($100), CGST (9%), SGST (9%).
     * Both should be calculated on the base $100.
     */
    public function test_concurrent_taxes_indian_gst_scenario()
    {
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

        // Each tax is 9% of 100 = 9.
        // Total Tax = 18.
        // Grand Total = 118.

        $this->assertEquals(100, $repository->sub_total);
        $this->assertEquals(18, $repository->tax_total);
        $this->assertEquals(118, $repository->grand_total);
        $this->assertCount(2, $repository->tax_lines);
        $this->assertEquals(9, $repository->tax_lines[0]['amount']);
        $this->assertEquals(9, $repository->tax_lines[1]['amount']);
    }

    /**
     * Scenario: Compounded Taxes with Discount
     * 1 Item ($100), 20% discount, Normal Tax (10%), Compounded Tax (5%).
     */
    public function test_compounded_taxes_with_discount_scenario()
    {
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

        // Price = 100. Discount = 20. Base = 80.
        // Normal Tax (10% of 80) = 8.
        // Compound Tax (5% of (80 + 8)) = 5% of 88 = 4.4.
        // Total Tax = 8 + 4.4 = 12.4.
        // Grand Total = 80 + 12.4 = 92.4.

        $this->assertEquals(100, $repository->sub_total);
        $this->assertEquals(20, $repository->discount_total);
        $this->assertEquals(8, $repository->default_tax_total);
        $this->assertEquals(12.4, $repository->tax_total);
        $this->assertEquals(92.4, $repository->grand_total);
    }
}
