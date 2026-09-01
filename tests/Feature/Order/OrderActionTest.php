<?php

uses(\Foundry\Tests\TestCase::class);

it("can create order using smart create", function () {
    $user = (\Foundry\Foundry::$userModel)::factory()->create();
    $data = ["customer_id" => $user->id, "sub_total" => 100, "tax_total" => 10, "grand_total" => 110, "line_items" => [["title" => "Product 1", "quantity" => 1, "price" => 100, "total" => 100]], "contact" => ["email" => $user->email, "phone_number" => "1234567890"]];
    $order = \Foundry\Models\Order::create($data);
    $this->assertDatabaseHas("orders", ["id" => $order->id, "customer_id" => $user->id, "grand_total" => 110]);
    $this->assertCount(1, $order->line_items);
    $this->assertEquals("Product 1", $order->line_items->first()->title);
    $this->assertNotNull($order->contact);
    $this->assertEquals("1234567890", $order->contact->phone_number);
});

it("can update order using smart update", function () {
    $user = (\Foundry\Foundry::$userModel)::factory()->create();
    $order = \Foundry\Models\Order::factory()->create(["customer_id" => $user->id]);
    $order->update(["grand_total" => 200, "note" => "Updated note"]);
    $this->assertEquals(200, $order->grand_total);
    $this->assertEquals("Updated note", $order->note);
});

it("can mark order as paid without retriggering smart update", function () {
    $user = (\Foundry\Foundry::$userModel)::factory()->create();
    $order = \Foundry\Models\Order::factory()->create(["customer_id" => $user->id, "grand_total" => 100, "paid_total" => 0]);
    $paidOrder = $order->markAsPaid();
    $this->assertEquals(\Foundry\Enum\PaymentStatus::PAID, $paidOrder->payment_status);
    $this->assertEquals(\Foundry\Enum\OrderStatus::PROCESSING, $paidOrder->status);
});

it("clears repository calculated cache when line items change", function () {
    $repository = new \Foundry\Repository\OrderRepository(["collect_tax" => false, "line_items" => [["title" => "Product 1", "quantity" => 1, "price" => 20]]]);
    $this->assertEquals(20, $repository->grand_total);
    $repository->line_items = [["title" => "Product 2", "quantity" => 3, "price" => 10]];
    $this->assertEquals(30, $repository->grand_total);
});

it("rolls back db transaction on failure", function () {
    $user = (\Foundry\Foundry::$userModel)::factory()->create();
    $data = ["customer_id" => $user->id, "line_items" => [["title" => "Product 1", "quantity" => 1, "price" => 100]], "discount" => ["type" => null]];
    $countBefore = \Foundry\Models\Order::count();
    try { \Foundry\Models\Order::create($data); } catch (\Exception $e) {}
    $this->assertEquals($countBefore, \Foundry\Models\Order::count());
});

it("get short codes contains all pdf fields", function () {
    $user = (\Foundry\Foundry::$userModel)::factory()->create();
    $order = \Foundry\Models\Order::factory()->create(["customer_id" => $user->id, "sub_total" => 100, "tax_total" => 10, "discount_total" => 5, "grand_total" => 105]);
    $shortCodes = $order->getShortCodes();
    foreach (["app_name", "logo", "id", "created_at", "billing_address", "line_items", "sub_total", "tax_total", "discount_total", "grand_total", "paid_total", "due_amount"] as $key) {
        $this->assertArrayHasKey($key, $shortCodes);
    }
    $this->assertStringContainsString("$", $shortCodes["grand_total"]);
});
