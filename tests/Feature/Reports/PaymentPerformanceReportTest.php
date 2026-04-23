<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Reports\Orders\PaymentPerformanceReport;
use Foundry\Tests\TestCase;

class PaymentPerformanceReportTest extends TestCase
{
    public function test_report_generates_payment_performance_data()
    {
        // Arrange
        $from = Carbon::now()->subMonths(1)->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        Order::factory()->create([
            'customer_id' => 1001,
            'status' => 'completed',
            'payment_status' => 'paid',
            'grand_total' => 100.00,
            'created_at' => $from->copy()->addDays(5),
        ]);

        Order::factory()->create([
            'customer_id' => 1002,
            'status' => 'pending',
            'payment_status' => 'failed',
            'grand_total' => 150.00,
            'created_at' => $from->copy()->addDays(10),
        ]);

        // Act
        $report = new PaymentPerformanceReport;
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
