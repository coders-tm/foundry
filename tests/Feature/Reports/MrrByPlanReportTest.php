<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Reports\Revenue\MrrByPlanReport;
use Foundry\Tests\TestCase;

class MrrByPlanReportTest extends TestCase
{
    public function test_report_generates_mrr_by_plan_data()
    {
        // Arrange
        $from = Carbon::now()->subMonths(1)->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $plan1 = Plan::factory()->create(['label' => 'Basic', 'price' => 30.00, 'interval' => 'month']);
        $plan2 = Plan::factory()->create(['label' => 'Pro', 'price' => 50.00, 'interval' => 'month']);

        Subscription::factory()->create([
            'user_id' => 1001,
            'plan_id' => $plan1->id,
            'type' => 'app',
            'status' => 'active',
            'quantity' => 1,
            'created_at' => $from->copy(),
            'starts_at' => $from->copy(),
        ]);

        Subscription::factory()->create([
            'user_id' => 1002,
            'plan_id' => $plan2->id,
            'type' => 'app',
            'status' => 'active',
            'quantity' => 1,
            'created_at' => $from->copy(),
            'starts_at' => $from->copy(),
        ]);

        // Act
        $report = new MrrByPlanReport;
        $filters = [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
        ];

        $result = $report->paginate($report->validate($filters), 25, 1);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
    }
}
