<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Metrics\AbstractMetric;
use Illuminate\Support\Facades\DB;

class CustomerSegmentsMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $segments = DB::table('users')
            ->select(
                DB::raw('CASE
                    WHEN total_spent >= 1000 THEN "high_value"
                    WHEN total_spent >= 100 THEN "medium_value"
                    WHEN total_spent > 0 THEN "low_value"
                    ELSE "no_purchase"
                END as segment'),
                DB::raw('COUNT(*) as count')
            )
            ->fromSub(function ($query) use ($end) {
                $query->from('users')
                    ->select('users.id')
                    ->selectRaw('COALESCE(SUM(orders.grand_total - COALESCE(orders.tax_total, 0)), 0) as total_spent')
                    ->leftJoin('orders', function ($join) use ($end) {
                        $join->on('users.id', '=', 'orders.customer_id')
                            ->where('orders.payment_status', Order::STATUS_PAID)
                            ->where('orders.created_at', '<=', $end);
                    })
                    ->groupBy('users.id');
            }, 'customer_totals')
            ->groupBy('segment')
            ->get()
            ->pluck('count', 'segment')
            ->toArray();

        return array_merge([
            'high_value' => 0,
            'medium_value' => 0,
            'low_value' => 0,
            'no_purchase' => 0,
        ], $segments);
    }

    /**
     * Get the value type.
     */
    public function type(): string
    {
        return 'array';
    }

    protected function defaultLabel(): string
    {
        return __('Customer Segments');
    }
}
