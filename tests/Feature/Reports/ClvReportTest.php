<?php

uses(\Foundry\Tests\TestCase::class);

it('report generates with customer lifetime value', function () {
    // Arrange: create users with orders
    $from = \Carbon\Carbon::now()->subMonths(6)->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    // Ensure clean tables
    // Create users using factory
    $user1 = \Workbench\App\Models\User::factory()->create(['id' => 1001, 'email' => 'customer1@example.com']);
    $user2 = \Workbench\App\Models\User::factory()->create(['id' => 1002, 'email' => 'customer2@example.com']);
    
    // Create orders (paid)
    \Foundry\Models\Order::factory()->create([
    'customer_id' => $user1->id,
    'status' => 'completed',
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 100.00,
    'created_at' => $from->copy()->addDays(5),
    ]);
    
    \Foundry\Models\Order::factory()->create([
    'customer_id' => $user1->id,
    'status' => 'completed',
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 150.00,
    'created_at' => $from->copy()->addMonths(2),
    ]);
    
    \Foundry\Models\Order::factory()->create([
    'customer_id' => $user2->id,
    'status' => 'completed',
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 200.00,
    'created_at' => $from->copy()->addMonth()->addDays(10),
    ]);
    
    // Act
    $report = new \Foundry\Services\Reports\Economics\ClvReport;
    $filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to' => $to->format('Y-m-d'),
    ];
    
    $result = $report->paginate($report->validate($filters), 25, 1);
    
    // Assert: report generates without errors
    $this->assertNotEmpty($result['data']);
    
    // Verify data structure
    foreach ($result['data'] as $row) {
    $this->assertArrayHasKey('user_id', $row);
    $this->assertArrayHasKey('user_email', $row);
    $this->assertArrayHasKey('total_revenue', $row);
    $this->assertArrayHasKey('avg_monthly_revenue', $row);
    $this->assertArrayHasKey('estimated_clv', $row);
    $this->assertArrayHasKey('order_count', $row);
    $this->assertIsNumeric($row['total_revenue']);
    $this->assertIsNumeric($row['estimated_clv']);
    }
});

it('summary calculates correctly', function () {
    // Arrange
    $from = \Carbon\Carbon::now()->subMonths(2)->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    // Create user using factory
    $user = \Workbench\App\Models\User::factory()->create(['id' => 1001, 'email' => 'customer@example.com']);
    
    \Foundry\Models\Order::factory()->create([
    'customer_id' => $user->id,
    'status' => 'completed',
    'payment_status' => \Foundry\Models\Order::STATUS_PAID,
    'grand_total' => 500.00,
    'created_at' => $from->copy()->addDays(5),
    ]);
    
    // Act
    $report = new \Foundry\Services\Reports\Economics\ClvReport;
    $filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to' => $to->format('Y-m-d'),
    ];
    
    $summary = $report->summarize($report->validate($filters));
    
    // Assert
    $this->assertArrayHasKey('total_customers', $summary);
    $this->assertArrayHasKey('average_clv', $summary);
    $this->assertArrayHasKey('total_projected_clv', $summary);
    $this->assertGreaterThanOrEqual(1, $summary['total_customers']);
});
