<?php

uses(\Foundry\Tests\TestCase::class);

it('calculate mrr excludes free forever', function () {
    // Arrange
    $date = \Carbon\Carbon::now();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 100]);

    $sub = \Foundry\Models\Subscription::factory()->create([
        'plan_id' => $plan->id,
        'status' => 'active',
        'is_free_forever' => 1,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
    ]);

    // Create a paid order just in case, but it should be ignored
    \Foundry\Models\Order::factory()->create([
        'orderable_id' => $sub->id,
        'orderable_type' => (new \Foundry\Foundry::$subscriptionModel)->getMorphClass(),
        'payment_status' => \Foundry\Models\Order::STATUS_PAID,
        'grand_total' => 100,
        'tax_total' => 0,
        'created_at' => $date->copy()->subDay(),
    ]);

    // Act
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $result = $metrics->only(['mrr']);
    $mrr = $result['mrr']['raw_current'];

    // Assert
    $this->assertEquals(0, $mrr, 'Actual MRR result: '.json_encode($result));
});

it('calculate mrr uses latest paid order minus tax', function () {
    // Arrange
    $date = \Carbon\Carbon::now();
    $plan = \Foundry\Models\Subscription\Plan::factory()->create(['price' => 100]);

    $sub = \Foundry\Models\Subscription::factory()->create([
        'plan_id' => $plan->id,
        'status' => 'active',
        'is_free_forever' => 0,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
    ]);

    // Old order
    \Foundry\Models\Order::factory()->create([
        'orderable_id' => $sub->id,
        'orderable_type' => (new \Foundry\Foundry::$subscriptionModel)->getMorphClass(),
        'payment_status' => \Foundry\Models\Order::STATUS_PAID,
        'grand_total' => 80, // Maybe an old price
        'tax_total' => 0,
        'created_at' => $date->copy()->subMonths(2),
    ]);

    // Latest order (with tax and discount)
    // Subscription is $100.
    // Discount $20 -> $80.
    // Tax $8 -> $88 Total.
    \Foundry\Models\Order::factory()->create([
        'orderable_id' => $sub->id,
        'orderable_type' => (new \Foundry\Foundry::$subscriptionModel)->getMorphClass(),
        'payment_status' => \Foundry\Models\Order::STATUS_PAID,
        'grand_total' => 88,
        'tax_total' => 8,
        'discount_total' => 20,
        'created_at' => $date->copy()->subDay(),
    ]);

    // Act
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $mrr = $metrics->only(['mrr'])['mrr']['raw_current'];

    // Assert: 88 (grand) - 8 (tax) = 80 per month
    $this->assertEquals(80, $mrr);
});

it('calculate mrr normalizes intervals', function () {
    // Arrange
    $date = \Carbon\Carbon::now();

    // Annual subscription: $1200 / year -> $100 MRR
    $sub = \Foundry\Models\Subscription::factory()->create([
        'status' => 'active',
        'billing_interval' => 'year',
        'billing_interval_count' => 1,
    ]);

    \Foundry\Models\Order::factory()->create([
        'orderable_id' => $sub->id,
        'orderable_type' => (new \Foundry\Foundry::$subscriptionModel)->getMorphClass(),
        'payment_status' => \Foundry\Models\Order::STATUS_PAID,
        'grand_total' => 1200,
        'tax_total' => 0,
        'created_at' => $date->copy()->subDay(),
    ]);

    // Act
    $metrics = new \Foundry\Services\Metrics\MetricsService([]);
    $mrr = $metrics->only(['mrr'])['mrr']['raw_current'];

    // Assert
    $this->assertEquals(100, $mrr);
});
