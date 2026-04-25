<?php

namespace Foundry\Tests\Unit\Repository;

use Foundry\Models\Order;
use Foundry\Models\Tax;
use Foundry\Repository\OrderRepository;
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class OrderRepositoryTest extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Scenario: Simple Taxable via fromRequest
     * 1 Taxable Item ($100), 10% Tax.
     */
    public function test_simple_taxable_from_request()
    {
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

        $this->assertEquals(100, $order->sub_total);
        $this->assertEquals(10, $order->tax_total);
        $this->assertEquals(110, $order->grand_total);
        $this->assertCount(1, $order->line_items);
        $this->assertEquals('Taxable Item', $order->line_items->first()->title);
    }

    /**
     * Scenario: Line Item Discounts via fromRequest
     */
    public function test_line_item_discount_from_request()
    {
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

        $this->assertEquals(100, $order->sub_total);
        $this->assertEquals(20, $order->discount_total);
        $this->assertEquals(8, $order->tax_total); // 10% of $80
        $this->assertEquals(88, $order->grand_total);

        $lineItem = $order->line_items->first();
        $this->assertNotNull($lineItem->discount);
        $this->assertEquals(20, $lineItem->discount->value);
    }

    /**
     * Scenario: Order Level Discount via fromRequest
     */
    public function test_order_level_discount_from_request()
    {
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

        $this->assertEquals(100, $order->sub_total);
        $this->assertEquals(20, $order->discount_total);
        $this->assertEquals(8, $order->tax_total);
        $this->assertEquals(88, $order->grand_total);
        $this->assertNotNull($order->discount);
        $this->assertEquals(20, $order->discount->value);
    }

    /**
     * Scenario: Customer and Address hydration
     */
    public function test_customer_and_address_hydration()
    {
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
            // OrderRepository expects line_items to be present
            'line_items' => [],
        ]);

        $order = OrderRepository::fromRequest($request, $order);

        $this->assertNotNull($order->customer);
        $this->assertEquals('John', $order->customer->first_name);
        $this->assertNotNull($order->customer->address);
        $this->assertEquals('123 Main St', $order->customer->address->line1);
    }

    /**
     * Scenario: Contact hydration
     */
    public function test_contact_hydration()
    {
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

        $this->assertNotNull($order->contact);
        $this->assertEquals('Jane', $order->contact->first_name);
        $this->assertEquals('jane@example.com', $order->contact->email);
    }

    /**
     * Scenario: Multiple Concurrent Taxes (Indian GST) resolved from DB
     */
    public function test_concurrent_taxes_indian_gst_from_request()
    {
        // Seed taxes for India
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
            // Omit tax_lines, let it resolve from DB
        ]);

        $order = OrderRepository::fromRequest($request, $order);

        $this->assertEquals(100, $order->sub_total);
        $this->assertEquals(18, $order->tax_total);
        $this->assertEquals(118, $order->grand_total);
        $this->assertCount(2, $order->tax_lines);
        $this->assertEquals('CGST', $order->tax_lines[0]['label']);
        $this->assertEquals('SGST', $order->tax_lines[1]['label']);
    }

    /**
     * Scenario: Compounded Taxes resolved from DB
     */
    public function test_compounding_taxes_from_request()
    {
        // Seed cascating taxes for a country
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

        // GST = 5% of 100 = 5.
        // PST = 10% of (100 + 5) = 10.5.
        // Total Tax = 5 + 10.5 = 15.5.
        // Grand Total = 115.5.

        $this->assertEquals(15.5, $order->tax_total);
        $this->assertEquals(115.5, $order->grand_total);
    }
}
