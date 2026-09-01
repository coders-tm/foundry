<?php

uses(\Foundry\Tests\TestCase::class);

it('report generates mrr movement data', function () {
    // Arrange
    $from = \Carbon\Carbon::now()->subMonths(2)->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    $plan = \Foundry\Models\Subscription\Plan::factory()->create();
    
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => 1001,
    'plan_id' => $plan->id,
    'type' => 'app',
    'status' => 'active',
    'quantity' => 1,
    'created_at' => $from->copy()->addDays(5),
    'starts_at' => $from->copy()->addDays(5),
    ]);
    
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => 1002,
    'plan_id' => $plan->id,
    'type' => 'app',
    'status' => 'cancelled',
    'quantity' => 1,
    'created_at' => $from->copy(),
    'starts_at' => $from->copy(),
    'cancels_at' => $from->copy()->addMonth()->addDays(10),
    ]);
    
    // Act
    $report = new \Foundry\Services\Reports\Revenue\MrrMovementReport;
    $filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to' => $to->format('Y-m-d'),
    'granularity' => 'monthly',
    ];
    
    $result = $report->paginate($report->validate($filters), 25, 1);
    
    // Assert
    $this->assertNotEmpty($result['data']);
});

it('summary calculates mrr changes', function () {
    // Arrange
    $from = \Carbon\Carbon::now()->subMonth()->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    $plan = \Foundry\Models\Subscription\Plan::factory()->create();
    
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => 1001,
    'plan_id' => $plan->id,
    'type' => 'app',
    'status' => 'active',
    'quantity' => 1,
    'created_at' => $from->copy()->addDays(5),
    'starts_at' => $from->copy()->addDays(5),
    ]);
    
    // Act
    $report = new \Foundry\Services\Reports\Revenue\MrrMovementReport;
    $filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to' => $to->format('Y-m-d'),
    ];
    
    $summary = $report->summarize($report->validate($filters));
    
    // Assert
    $this->assertIsArray($summary);
});
