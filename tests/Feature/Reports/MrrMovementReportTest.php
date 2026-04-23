<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Reports\Revenue\MrrMovementReport;
use Foundry\Tests\TestCase;

class MrrMovementReportTest extends TestCase
{
    public function test_report_generates_mrr_movement_data()
    {
        // Arrange
        $from = Carbon::now()->subMonths(2)->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $plan = Plan::factory()->create();

        Subscription::factory()->create([
            'user_id' => 1001,
            'plan_id' => $plan->id,
            'type' => 'app',
            'status' => 'active',
            'quantity' => 1,
            'created_at' => $from->copy()->addDays(5),
            'starts_at' => $from->copy()->addDays(5),
        ]);

        Subscription::factory()->create([
            'user_id' => 1002,
            'plan_id' => $plan->id,
            'type' => 'app',
            'status' => 'cancelled',
            'quantity' => 1,
            'created_at' => $from->copy(),
            'starts_at' => $from->copy(),
            'canceled_at' => $from->copy()->addMonth()->addDays(10),
        ]);

        // Act
        $report = new MrrMovementReport;
        $filters = [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'granularity' => 'monthly',
        ];

        $result = $report->paginate($report->validate($filters), 25, 1);

        // Assert
        $this->assertNotEmpty($result['data']);
    }

    public function test_summary_calculates_mrr_changes()
    {
        // Arrange
        $from = Carbon::now()->subMonth()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $plan = Plan::factory()->create();

        Subscription::factory()->create([
            'user_id' => 1001,
            'plan_id' => $plan->id,
            'type' => 'app',
            'status' => 'active',
            'quantity' => 1,
            'created_at' => $from->copy()->addDays(5),
            'starts_at' => $from->copy()->addDays(5),
        ]);

        // Act
        $report = new MrrMovementReport;
        $filters = [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
        ];

        $summary = $report->summarize($report->validate($filters));

        // Assert
        $this->assertIsArray($summary);
    }
}
