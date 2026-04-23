<?php

namespace Foundry\Tests\Feature\Order;

use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Tests\TestCase;

class OrderActionTest extends TestCase
{
    public function test_can_create_order_using_smart_create()
    {
        $user = (Foundry::$userModel)::factory()->create();
        $data = [
            'customer_id' => $user->id,
            'sub_total' => 100,
            'tax_total' => 10,
            'grand_total' => 110,
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'quantity' => 1,
                    'price' => 100,
                    'total' => 100,
                ],
            ],
            'contact' => [
                'email' => $user->email,
                'phone_number' => '1234567890',
            ],
        ];

        $order = Order::create($data);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => $user->id,
            'grand_total' => 110,
        ]);

        $this->assertCount(1, $order->line_items);
        $this->assertEquals('Product 1', $order->line_items->first()->title);
        $this->assertNotNull($order->contact);
        $this->assertEquals('1234567890', $order->contact->phone_number);
    }

    public function test_can_update_order_using_smart_create()
    {
        $user = (Foundry::$userModel)::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user->id]);

        $data = [
            'id' => $order->id,
            'grand_total' => 200,
            'note' => 'Updated note',
        ];

        $updatedOrder = Order::create($data);

        $this->assertEquals($order->id, $updatedOrder->id);
        $this->assertEquals(200, $updatedOrder->grand_total);
        $this->assertEquals('Updated note', $updatedOrder->note);
    }

    public function test_db_transaction_on_failure()
    {
        $user = (Foundry::$userModel)::factory()->create();
        $data = [
            'customer_id' => $user->id,
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'quantity' => 1,
                    'price' => 100,
                ],
            ],
            'discount' => [
                'type' => null, // Should fail NOT NULL constraint
            ],
        ];

        $countBefore = Order::count();

        try {
            Order::create($data);
        } catch (\Exception $e) {
            // Expected failure
        }

        $this->assertEquals($countBefore, Order::count(), 'Order should not be created if related data fails');
    }

    public function test_get_short_codes_contains_all_pdf_fields()
    {
        $user = (Foundry::$userModel)::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'sub_total' => 100,
            'tax_total' => 10,
            'discount_total' => 5,
            'grand_total' => 105,
        ]);

        $shortCodes = $order->getShortCodes();

        $this->assertArrayHasKey('app_name', $shortCodes);
        $this->assertArrayHasKey('logo', $shortCodes);
        $this->assertArrayHasKey('id', $shortCodes);
        $this->assertArrayHasKey('created_at', $shortCodes);
        $this->assertArrayHasKey('billing_address', $shortCodes);
        $this->assertArrayHasKey('line_items', $shortCodes);
        $this->assertArrayHasKey('sub_total', $shortCodes);
        $this->assertArrayHasKey('tax_total', $shortCodes);
        $this->assertArrayHasKey('discount_total', $shortCodes);
        $this->assertArrayHasKey('grand_total', $shortCodes);
        $this->assertArrayHasKey('paid_total', $shortCodes);
        $this->assertArrayHasKey('due_amount', $shortCodes);

        // Check formatted values
        $this->assertStringContainsString('$', $shortCodes['grand_total']); // Assuming USD is default
    }
}
