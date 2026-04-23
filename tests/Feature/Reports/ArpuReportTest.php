<?php

namespace Foundry\Tests\Feature\Reports;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Services\Reports\Economics\ArpuReport;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ArpuReportTest extends TestCase
{
    public function test_period_labels_are_correct_strings()
    {
        // Arrange: create minimal data across two months
        $from = Carbon::now()->subMonths(2)->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        // Ensure clean tables
        DB::table('orders')->truncate();
        DB::table('subscriptions')->truncate();

        // Create subscriptions (active users)
        Subscription::factory()->create([
            'user_id' => 1001,
            'plan_id' => 1,
            'type' => 'app',
            'status' => 'active',
            'quantity' => 1,
            'created_at' => $from->copy()->addDays(1),
            'starts_at' => $from->copy()->addDays(1),
        ]);

        Subscription::factory()->create([
            'user_id' => 1002,
            'plan_id' => 1,
            'type' => 'app',
            'status' => 'active',
            'quantity' => 1,
            'created_at' => $from->copy()->addDays(2),
            'starts_at' => $from->copy()->addDays(2),
        ]);

        // Create orders (paid)
        Order::factory()->create([
            'customer_id' => 1001,
            'status' => 'completed',
            'payment_status' => Order::STATUS_PAID,
            'grand_total' => 100.00,
            'created_at' => $from->copy()->addDays(5),
        ]);

        Order::factory()->create([
            'customer_id' => 1002,
            'status' => 'completed',
            'payment_status' => Order::STATUS_PAID,
            'grand_total' => 50.00,
            'created_at' => $from->copy()->addMonth()->addDays(5),
        ]);

        // Act
        $report = new ArpuReport;
        $filters = [
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'granularity' => 'monthly',
        ];

        $result = $report->paginate($report->validate($filters), 25, 1);

        // Assert: period labels are strings like YYYY-MM, not integers or 'completed'
        $this->assertNotEmpty($result['data']);

        foreach ($result['data'] as $row) {
            $this->assertIsString($row['period']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $row['period']);
            $this->assertNotEquals('completed', $row['period']);
        }
    }
}
