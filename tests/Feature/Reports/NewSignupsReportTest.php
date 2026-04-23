<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Services\Reports\Acquisition\NewSignupsReport;
use Foundry\Tests\TestCase;
use Workbench\App\Models\User;

class NewSignupsReportTest extends TestCase
{
    public function test_report_generates_new_signups_by_period()
    {
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
    }

    public function test_summary_calculates_totals()
    {
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
    }
}
