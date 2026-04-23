<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Metrics\AbstractMetric;
use Illuminate\Support\Facades\DB;

class AovMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $stats = DB::table('orders')
            ->where('payment_status', Order::STATUS_PAID)
            ->whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(grand_total) as total')
            )
            ->first();

        return $stats->count > 0 ? round($stats->total / $stats->count, 2) : 0.0;
    }

    /**
     * Get the value type.
     */
    public function type(): string
    {
        return 'currency';
    }

    protected function defaultLabel(): string
    {
        return __('Average Order Value (AOV)');
    }
}
