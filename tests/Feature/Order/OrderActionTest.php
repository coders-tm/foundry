<?php

namespace Foundry\Tests\Feature\Order;

use Foundry\Enum\OrderStatus as OrderStatusEnum;
use Foundry\Enum\PaymentStatus;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Repository\OrderRepository;
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

    public function test_can_update_order_using_smart_update()
    {
        $user = (Foundry::$userModel)::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user->id]);

        $data = [
            'grand_total' => 200,
            'note' => 'Updated note',
        ];

        $order->update($data);

        $this->assertEquals(200, $order->grand_total);
        $this->assertEquals('Updated note', $order->note);
    }

    public function test_can_mark_order_as_paid_without_retriggering_smart_update()
    {
        $user = (Foundry::$userModel)::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $user->id,
            'grand_total' => 100,
            'paid_total' => 0,
        ]);

        $paidOrder = $order->markAsPaid();

        $this->assertEquals(PaymentStatus::PAID, $paidOrder->payment_status);
        $this->assertEquals(OrderStatusEnum::PROCESSING, $paidOrder->status);
    }

    public function test_repository_calculated_cache_is_cleared_when_line_items_change()
    {
        $repository = new OrderRepository([
            'collect_tax' => false,
            'line_items' => [
                [
                    'title' => 'Product 1',
                    'quantity' => 1,
                    'price' => 20,
                ],
            ],
        ]);

        $this->assertEquals(20, $repository->grand_total);

        $repository->line_items = [
            [
                'title' => 'Product 2',
                'quantity' => 3,
                'price' => 10,
            ],
        ];

        $this->assertEquals(30, $repository->grand_total);
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
