<?php

namespace Tests\Feature\Subscription;

use Foundry\Models\Coupon;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Foundry\Tests\TestCase;

class InvoiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_invoice_calculations()
    {
        // 1. Setup: Create a plan with price $100
        $plan = Plan::factory()->create([
            'price' => 100.00,
            'label' => 'Monthly Plan',
            'interval' => 'month',
            'interval_count' => 1,
        ]);

        // 2. Setup: Create a coupon with 20% discount
        $coupon = Coupon::factory()->create([
            'name' => 'Save 20',
            'value' => 20,
            'discount_type' => 'percentage',
            'promotion_code' => 'SAVE20',
        ]);

        // 3. Setup: Create a user and subscription with the coupon
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'coupon_id' => $coupon->id,
        ]);

        // 4. Action: Get upcoming invoice
        $upcomingInvoice = $subscription->upcomingInvoice();

        // 5. Verification:
        // Expected: Subtotal 100, Discount 20, Grand Total 80 (assuming 0 tax for now)
        $this->assertNotNull($upcomingInvoice);

        // Asserting the values that the user EXPECTS (which are currently different)
        $this->assertEquals(100.00, $upcomingInvoice->sub_total, 'Sub total should be the gross amount ($100)');
        $this->assertEquals(8.00, $upcomingInvoice->tax_total, 'Tax total should be correct ($8)');
        $this->assertEquals(20.00, $upcomingInvoice->discount_total, 'Discount total should include line-item discounts ($20)');
        $this->assertEquals(88.00, $upcomingInvoice->grand_total, 'Grand total should be correct ($88)');
    }

    public function test_generated_invoice_calculations()
    {
        // 1. Setup: Create a plan with price $100
        $plan = Plan::factory()->create([
            'price' => 100.00,
            'label' => 'Monthly Plan',
            'interval' => 'month',
            'interval_count' => 1,
        ]);

        // 2. Setup: Create a coupon with $15 fixed discount
        $coupon = Coupon::factory()->create([
            'name' => 'Save 15',
            'value' => 15,
            'discount_type' => 'fixed',
            'promotion_code' => 'SAVE15',
        ]);

        // 3. Setup: Create a user and subscription
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'coupon_id' => $coupon->id,
        ]);

        // 4. Action: Generate invoice
        $invoice = $subscription->generateInvoice();

        // 5. Verification:
        $this->assertNotNull($invoice);
        $this->assertEquals(100.00, $invoice->sub_total, 'Stored sub_total should be the gross amount ($100)');
        $this->assertEquals(8.5, $invoice->tax_total, 'Stored tax_total should be the total tax ($10)');
        $this->assertEquals(15.00, $invoice->discount_total, 'Stored discount_total should be the total discount ($15)');
        $this->assertEquals(93.50, $invoice->grand_total, 'Stored grand_total should be correct ($93.50)');
    }
}
