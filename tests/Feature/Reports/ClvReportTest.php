<?php

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Reports\Economics\ClvReport;
use Foundry\Tests\TestCase;
use Workbench\App\Models\User;

uses(TestCase::class);

it('report generates with customer lifetime value', function () {
    // Arrange: create users with orders
    $from = Carbon::now()->subMonths(6)->startOfMonth();
    $to = Carbon::now()->endOfMonth();

    // Ensure clean tables
    // Create users using factory
    $user1 = User::factory()->create(['id' => 1001, 'email' => 'customer1@example.com']);
    $user2 = User::factory()->create(['id' => 1002, 'email' => 'customer2@example.com']);

    // Create orders (paid)
    Order::factory()->create([
        'customer_id' => $user1->id,
        'status' => 'completed',
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 100.00,
        'created_at' => $from->copy()->addDays(5),
    ]);

    Order::factory()->create([
        'customer_id' => $user1->id,
        'status' => 'completed',
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 150.00,
        'created_at' => $from->copy()->addMonths(2),
    ]);

    Order::factory()->create([
        'customer_id' => $user2->id,
        'status' => 'completed',
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 200.00,
        'created_at' => $from->copy()->addMonth()->addDays(10),
    ]);

    // Act
    $report = new ClvReport;
    $filters = [
        'date_from' => $from->format('Y-m-d'),
        'date_to' => $to->format('Y-m-d'),
    ];

    $result = $report->paginate($report->validate($filters), 25, 1);

    // Assert: report generates without errors
    expect($result['data'])->not->toBeEmpty();

    // Verify data structure
    foreach ($result['data'] as $row) {
        expect($row)->toHaveKey('user_id');
        expect($row)->toHaveKey('user_email');
        expect($row)->toHaveKey('total_revenue');
        expect($row)->toHaveKey('avg_monthly_revenue');
        expect($row)->toHaveKey('estimated_clv');
        expect($row)->toHaveKey('order_count');
        expect($row['total_revenue'])->toBeNumeric();
        expect($row['estimated_clv'])->toBeNumeric();
    }
});

it('summary calculates correctly', function () {
    // Arrange
    $from = Carbon::now()->subMonths(2)->startOfMonth();
    $to = Carbon::now()->endOfMonth();

    // Create user using factory
    $user = User::factory()->create(['id' => 1001, 'email' => 'customer@example.com']);

    Order::factory()->create([
        'customer_id' => $user->id,
        'status' => 'completed',
        'payment_status' => Order::STATUS_PAID,
        'grand_total' => 500.00,
        'created_at' => $from->copy()->addDays(5),
    ]);

    // Act
    $report = new ClvReport;
    $filters = [
        'date_from' => $from->format('Y-m-d'),
        'date_to' => $to->format('Y-m-d'),
    ];

    $summary = $report->summarize($report->validate($filters));

    // Assert
    expect($summary)->toHaveKey('total_customers');
    expect($summary)->toHaveKey('average_clv');
    expect($summary)->toHaveKey('total_projected_clv');
    expect($summary['total_customers'])->toBeGreaterThanOrEqual(1);
});
