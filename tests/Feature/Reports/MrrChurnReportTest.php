<?php

uses(\Foundry\Tests\TestCase::class);

it('report generates mrr churn data', function () {
    // Arrange
    $from = \Carbon\Carbon::now()->subMonths(2)->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    $plan = \Foundry\Models\Subscription\Plan::factory()->create();
    
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => 1001,
    'plan_id' => $plan->id,
    'type' => 'app',
    'status' => 'cancelled',
    'quantity' => 1,
    'cancels_at' => $from->copy()->addDays(15),
    'created_at' => $from->copy(),
    'starts_at' => $from->copy(),
    ]);
    
    // Act
    $report = new \Foundry\Services\Reports\Retention\MrrChurnReport;
    $filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to' => $to->format('Y-m-d'),
    'granularity' => 'monthly',
    ];
    
    $result = $report->paginate($report->validate($filters), 25, 1);
    
    // Assert
    $this->assertIsArray($result);
    $this->assertArrayHasKey('data', $result);
});
