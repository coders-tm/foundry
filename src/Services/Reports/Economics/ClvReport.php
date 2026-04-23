<?php

namespace Foundry\Services\Reports\Economics;

use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * CLV (Customer Lifetime Value) Report
 *
 * Calculates lifetime value by analyzing order history and projecting future revenue.
 * Estimated CLV = Avg monthly revenue × 24 months expected lifetime.
 */
class ClvReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'user_id' => ['label' => 'User ID', 'type' => 'number'],
        'user_email' => ['label' => 'Email', 'type' => 'text'],
        'first_order' => ['label' => 'First Order', 'type' => 'text'],
        'months_active' => ['label' => 'Months Active', 'type' => 'number'],
        'total_revenue' => ['label' => 'Total Revenue', 'type' => 'currency'],
        'avg_monthly_revenue' => ['label' => 'Avg Monthly Revenue', 'type' => 'currency'],
        'estimated_clv' => ['label' => 'Estimated CLV', 'type' => 'currency'],
        'order_count' => ['label' => 'Total Orders', 'type' => 'number'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'clv';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Calculate customer lifetime value and revenue projections';
    }

    /**
     * Build efficient query with aggregated order stats.
     *
     * Uses database-agnostic dbDateDiffMonths() for cross-database compatibility.
     *
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        $userTable = (new (Foundry::$userModel))->getTable();
        $now = now()->toDateTimeString();

        // Database-agnostic months diff calculation
        $monthsDiffExpression = $this->dbDateDiffMonths("'{$now}'", 'MIN(orders.created_at)');

        return DB::table($userTable)
            ->join('orders', 'orders.customer_id', '=', "{$userTable}.id")
            ->where('orders.payment_status', Order::STATUS_PAID)
            ->whereBetween('orders.created_at', [$filters['from'], $filters['to']])
            ->select([
                "{$userTable}.id as user_id",
                "{$userTable}.email as user_email",
                DB::raw('MIN(orders.created_at) as first_order_date'),
                DB::raw("{$monthsDiffExpression} as months_active"),
                DB::raw('COALESCE(SUM(orders.grand_total), 0) as total_revenue'),
                DB::raw('COUNT(orders.id) as order_count'),
            ])
            ->groupBy("{$userTable}.id", "{$userTable}.email")
            ->orderBy("{$userTable}.id");
    }

    /**
     * Transform row to array with raw numeric values.
     *
     * {@inheritdoc}
     */
    public function toRow($row): array
    {
        $monthsActive = max(1, (int) ($row->months_active ?? 1));
        $totalRevenue = (float) ($row->total_revenue ?? 0);
        $avgMonthlyRevenue = $totalRevenue / $monthsActive;

        // Simple CLV estimation: avg monthly * expected lifetime (24 months default)
        $expectedLifetimeMonths = 24;
        $estimatedClv = $avgMonthlyRevenue * $expectedLifetimeMonths;

        return [
            'user_id' => (int) ($row->user_id ?? 0),
            'user_email' => $row->user_email ?? '',
            'first_order' => $row->first_order_date ?? '',
            'months_active' => $monthsActive,
            'total_revenue' => $totalRevenue,
            'avg_monthly_revenue' => (float) $avgMonthlyRevenue,
            'estimated_clv' => (float) $estimatedClv,
            'order_count' => (int) ($row->order_count ?? 0),
        ];
    }

    /**
     * Calculate summary statistics.
     *
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $now = now()->toDateTimeString();
        $oneMonthAgo = now()->subMonth()->toDateTimeString();

        // Get aggregated stats for all users with orders in the period
        $totalCustomers = DB::table('orders')
            ->where('payment_status', Order::STATUS_PAID)
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->distinct('customer_id')
            ->count('customer_id');

        // Calculate average monthly revenue (revenue from last month / unique customers)
        $lastMonthRevenue = DB::table('orders')
            ->where('payment_status', Order::STATUS_PAID)
            ->whereRaw('created_at >= ?', [$oneMonthAgo])
            ->whereRaw('created_at <= ?', [$now])
            ->sum('grand_total');

        $avgMonthlyPerCustomer = $totalCustomers > 0 ? $lastMonthRevenue / $totalCustomers : 0;

        // Estimate total CLV: avg monthly per customer * expected lifetime (24 months)
        $totalProjectedClv = $avgMonthlyPerCustomer * $totalCustomers * 24;
        $averageClv = $avgMonthlyPerCustomer * 24;

        return [
            'total_customers' => (int) $totalCustomers,
            'average_clv' => format_amount($averageClv),
            'total_projected_clv' => format_amount($totalProjectedClv),
        ];
    }
}
