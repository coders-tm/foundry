<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Reports\Orders\TaxSummaryReport;
use Foundry\Tests\TestCase;

class TaxSummaryReportTest extends TestCase
{
    public function test_report_generates_tax_summary_data()
    {
        // Arrange
        $from = Carbon::now()->subMonths(1)->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        Order::factory()->create([
            'customer_id' => 1001,
            'status' => 'completed',
            'payment_status' => 'paid',
            'grand_total' => 100.00,
            'tax_total' => 10.00,
            'created_at' => $from->copy()->addDays(5),
        ]);

        Order::factory()->create([
            'customer_id' => 1002,
            'status' => 'completed',
            'payment_status' => 'paid',
            'grand_total' => 200.00,
            'tax_total' => 20.00,
            'created_at' => $from->copy()->addDays(10),
        ]);

        // Act
        $report = new TaxSummaryReport;
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
