<?php

use Carbon\Carbon;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Reports\Retention\CustomerChurnReport;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('report generates churn data', function () {
    // Arrange
    $from = Carbon::now()->subMonths(2)->startOfMonth();
    $to = Carbon::now()->endOfMonth();

    $plan = Plan::factory()->create();

    Subscription::factory()->create([
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
    $report = new CustomerChurnReport;
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
