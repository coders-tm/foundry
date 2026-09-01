<?php

use Carbon\Carbon;
use Foundry\Services\Reports\Acquisition\NewSignupsReport;
use Foundry\Tests\TestCase;
use Workbench\App\Models\User;

uses(TestCase::class);

it('report generates new signups by period', function () {
    // Arrange
    $from = Carbon::now()->subMonths(2)->startOfMonth();
    $to = Carbon::now()->endOfMonth();

    // Create users across different months
    User::factory()->create(['created_at' => $from->copy()->addDays(5)]);
    User::factory()->create(['created_at' => $from->copy()->addDays(10)]);
    User::factory()->create(['created_at' => $from->copy()->addMonth()->addDays(3)]);

    // Act
    $report = new NewSignupsReport;
    $filters = [
        'date_from' => $from->format('Y-m-d'),
        'date_to' => $to->format('Y-m-d'),
        'granularity' => 'monthly',
    ];

    $result = $report->paginate($report->validate($filters), 25, 1);

    // Assert
    $this->assertNotEmpty($result['data']);
    foreach ($result['data'] as $row) {
        $this->assertArrayHasKey('period', $row);
        $this->assertArrayHasKey('new_users', $row);
        $this->assertIsNumeric($row['new_users']);
    }
});

it('summary calculates totals', function () {
    // Arrange
    $from = Carbon::now()->subMonth()->startOfMonth();
    $to = Carbon::now()->endOfMonth();

    User::factory()->count(5)->create(['created_at' => $from->copy()->addDays(5)]);

    // Act
    $report = new NewSignupsReport;
    $filters = [
        'date_from' => $from->format('Y-m-d'),
        'date_to' => $to->format('Y-m-d'),
    ];

    $summary = $report->summarize($report->validate($filters));

    // Assert
    $this->assertArrayHasKey('total_new_users', $summary);
    $this->assertGreaterThanOrEqual(5, $summary['total_new_users']);
});
