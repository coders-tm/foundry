<?php

uses(\Foundry\Tests\TestCase::class);

it('report generates tax summary data', function () {
    // Arrange
    $from = \Carbon\Carbon::now()->subMonths(1)->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    \Foundry\Models\Order::factory()->create([
    'customer_id' => 1001,
    'status' => 'completed',
    'payment_status' => 'paid',
    'grand_total' => 100.00,
    'tax_total' => 10.00,
    'created_at' => $from->copy()->addDays(5),
    ]);
    
    \Foundry\Models\Order::factory()->create([
    'customer_id' => 1002,
    'status' => 'completed',
    'payment_status' => 'paid',
    'grand_total' => 200.00,
    'tax_total' => 20.00,
    'created_at' => $from->copy()->addDays(10),
    ]);
    
    // Act
    $report = new \Foundry\Services\Reports\Orders\TaxSummaryReport;
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
