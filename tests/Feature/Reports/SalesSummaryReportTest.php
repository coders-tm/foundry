<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Reports\Orders\SalesSummaryReport;
use Foundry\Tests\TestCase;

class SalesSummaryReportTest extends TestCase
{
    public function test_report_generates_sales_summary_data()
    {
        // Arrange
        $from = Carbon::now()->subMonths(1)->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        Order::factory()->create([
            'customer_id' => 1001,
            'status' => 'completed',
            'payment_status' => 'paid',
            'grand_total' => 250.00,
            'created_at' => $from->copy()->addDays(5),
        ]);

        Order::factory()->create([
            'customer_id' => 1002,
            'status' => 'completed',
            'payment_status' => 'paid',
            'grand_total' => 300.00,
            'created_at' => $from->copy()->addDays(10),
        ]);

        // Act
        $report = new SalesSummaryReport;
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
            $this->assertArrayHasKey('total_orders', $row);
            $this->assertArrayHasKey('gmv', $row);
            $this->assertArrayHasKey('net_revenue', $row);
        }
    }

    public function test_summary_calculates_totals()
    {
        // Arrange
        $from = Carbon::now()->subMonth()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        Order::factory()->create([
            'customer_id' => 1001,
            'status' => 'completed',
            'payment_status' => 'paid',
            'grand_total' => 500.00,
            'created_at' => $from->copy()->addDays(5),
        ]);

        // Act
        $report = new SalesSummaryReport;
        $filters = [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
        ];

        $summary = $report->summarize($report->validate($filters));

        // Assert
        $this->assertIsArray($summary);
    }
}
