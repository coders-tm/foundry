<?php

uses(\Foundry\Tests\TestCase::class);

it('period labels are correct strings', function () {
    // Arrange: create minimal data across two months
    $from = \Carbon\Carbon::now()->subMonths(2)->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    // Ensure clean tables
    \Illuminate\Support\Facades\DB::table('orders')->truncate();
    \Illuminate\Support\Facades\DB::table('subscriptions')->truncate();
    
    // Create subscriptions (active users)
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => 1001,
    'plan_id' => 1,
    'type' => 'app',
    'status' => 'active',
    'quantity' => 1,
    'created_at' => $from->copy()->addDays(1),
    'starts_at' => $from->copy()->addDays(1),
    ]);
    
    \Foundry\Models\Subscription::factory()->create([
    'user_id' => 1002,
    'plan_id' => 1,
    'type' => 'app',
    'status' => 'active',
    'quantity' => 1,
    'created_at' => $from->copy()->addDays(2),
    'starts_at' => $from->copy()->addDays(2),
    ]);
    
    // Create orders (paid)
    \Foundry\Models\Order::factory()->create([
    'customer_id' => 1001,
    'status' => 'completed',
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 100.00,
    'created_at' => $from->copy()->addDays(5),
    ]);
    
    \Foundry\Models\Order::factory()->create([
    'customer_id' => 1002,
    'status' => 'completed',
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 50.00,
    'created_at' => $from->copy()->addMonth()->addDays(5),
    ]);
    
    // Act
    $report = new \Foundry\Services\Reports\Economics\ArpuReport;
    $filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to' => $to->format('Y-m-d'),
    'granularity' => 'monthly',
    ];
    
    $result = $report->paginate($report->validate($filters), 25, 1);
    
    // Assert: period labels are strings like YYYY-MM, not integers or 'completed'
    $this->assertNotEmpty($result['data']);
    
    foreach ($result['data'] as $row) {
    $this->assertIsString($row['period']);
    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $row['period']);
    $this->assertNotEquals('completed', $row['period']);
    }
});
