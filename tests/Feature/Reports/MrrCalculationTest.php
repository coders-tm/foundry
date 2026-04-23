<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Metrics\MetricsService;
use Foundry\Tests\TestCase;

class MrrCalculationTest extends TestCase
{
    public function test_calculate_mrr_excludes_free_forever()
    {
        // Arrange
        $date = Carbon::now();
        $plan = Plan::factory()->create(['price' => 100]);

        $sub = Subscription::factory()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'is_free_forever' => 1,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
        ]);

        // Create a paid order just in case, but it should be ignored
        Order::factory()->create([
            'orderable_id' => $sub->id,
            'orderable_type' => (new Subscription)->getMorphClass(),
            'payment_status' => Order::STATUS_PAID,
            'grand_total' => 100,
            'tax_total' => 0,
            'created_at' => $date->copy()->subDay(),
        ]);

        // Act
        $metrics = new MetricsService([]);
        $result = $metrics->only(['mrr']);
        $mrr = $result['mrr']['raw_current'];

        // Assert
        $this->assertEquals(0, $mrr, 'Actual MRR result: '.json_encode($result));
    }

    public function test_calculate_mrr_uses_latest_paid_order_minus_tax()
    {
        // Arrange
        $date = Carbon::now();
        $plan = Plan::factory()->create(['price' => 100]);

        $sub = Subscription::factory()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'is_free_forever' => 0,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
        ]);

        // Old order
        Order::factory()->create([
            'orderable_id' => $sub->id,
            'orderable_type' => (new Subscription)->getMorphClass(),
            'payment_status' => Order::STATUS_PAID,
            'grand_total' => 80, // Maybe an old price
            'tax_total' => 0,
            'created_at' => $date->copy()->subMonths(2),
        ]);

        // Latest order (with tax and discount)
        // Subscription is $100.
        // Discount $20 -> $80.
        // Tax $8 -> $88 Total.
        Order::factory()->create([
            'orderable_id' => $sub->id,
            'orderable_type' => (new Subscription)->getMorphClass(),
            'payment_status' => Order::STATUS_PAID,
            'grand_total' => 88,
            'tax_total' => 8,
            'discount_total' => 20,
            'created_at' => $date->copy()->subDay(),
        ]);

        // Act
        $metrics = new MetricsService([]);
        $mrr = $metrics->only(['mrr'])['mrr']['raw_current'];

        // Assert: 88 (grand) - 8 (tax) = 80 per month
        $this->assertEquals(80, $mrr);
    }

    public function test_calculate_mrr_normalizes_intervals()
    {
        // Arrange
        $date = Carbon::now();

        // Annual subscription: $1200 / year -> $100 MRR
        $sub = Subscription::factory()->create([
            'status' => 'active',
            'billing_interval' => 'year',
            'billing_interval_count' => 1,
        ]);

        Order::factory()->create([
            'orderable_id' => $sub->id,
            'orderable_type' => (new Subscription)->getMorphClass(),
            'payment_status' => Order::STATUS_PAID,
            'grand_total' => 1200,
            'tax_total' => 0,
            'created_at' => $date->copy()->subDay(),
        ]);

        // Act
        $metrics = new MetricsService([]);
        $mrr = $metrics->only(['mrr'])['mrr']['raw_current'];

        // Assert
        $this->assertEquals(100, $mrr);
    }
}
