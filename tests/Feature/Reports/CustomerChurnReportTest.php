<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Reports\Retention\CustomerChurnReport;
use Foundry\Tests\TestCase;

class CustomerChurnReportTest extends TestCase
{
    public function test_report_generates_churn_data()
    {
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
            'canceled_at' => $from->copy()->addDays(15),
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
    }
}
