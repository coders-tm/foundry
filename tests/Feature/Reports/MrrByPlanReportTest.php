<?php

uses(\Foundry\Tests\TestCase::class);

it('report generates mrr by plan data', function () {
    // Arrange
    $from = \Carbon\Carbon::now()->subMonths(1)->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    $plan1 = \Foundry\Models\Subscription\Plan::factory()->create(['label' => 'Basic', 'price' => 30.00, 'interval' => 'month']);
    $plan2 = \Foundry\Models\Subscription\Plan::factory()->create(['label' => 'Pro', 'price' => 50.00, 'interval' => 'month']);
    
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => 1001,
    'plan_id' => $plan1->id,
    'type' => 'app',
    'status' => 'active',
    'quantity' => 1,
    'created_at' => $from->copy(),
    'starts_at' => $from->copy(),
    ]);
    
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => 1002,
    'plan_id' => $plan2->id,
    'type' => 'app',
    'status' => 'active',
    'quantity' => 1,
    'created_at' => $from->copy(),
    'starts_at' => $from->copy(),
    ]);
    
    // Act
    $report = new \Foundry\Services\Reports\Revenue\MrrByPlanReport;
    $filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to' => $to->format('Y-m-d'),
    ];
    
    $result = $report->paginate($report->validate($filters), 25, 1);
    
    // Assert
    $this->assertIsArray($result);
    $this->assertArrayHasKey('data', $result);
});
