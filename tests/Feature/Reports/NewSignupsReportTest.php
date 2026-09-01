<?php

uses(\Foundry\Tests\TestCase::class);

it('report generates new signups by period', function () {
    // Arrange
    $from = \Carbon\Carbon::now()->subMonths(2)->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    // Create users across different months
    \Workbench\App\Models\User::factory()->create(['created_at' => $from->copy()->addDays(5)]);
    \Workbench\App\Models\User::factory()->create(['created_at' => $from->copy()->addDays(10)]);
    \Workbench\App\Models\User::factory()->create(['created_at' => $from->copy()->addMonth()->addDays(3)]);
    
    // Act
    $report = new \Foundry\Services\Reports\Acquisition\NewSignupsReport;
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
    $from = \Carbon\Carbon::now()->subMonth()->startOfMonth();
    $to = \Carbon\Carbon::now()->endOfMonth();
    
    \Workbench\App\Models\User::factory()->count(5)->create(['created_at' => $from->copy()->addDays(5)]);
    
    // Act
    $report = new \Foundry\Services\Reports\Acquisition\NewSignupsReport;
    $filters = [
    'date_from' => $from->format('Y-m-d'),
    'date_to' => $to->format('Y-m-d'),
    ];
    
    $summary = $report->summarize($report->validate($filters));
    
    // Assert
    $this->assertArrayHasKey('total_new_users', $summary);
    $this->assertGreaterThanOrEqual(5, $summary['total_new_users']);
});
