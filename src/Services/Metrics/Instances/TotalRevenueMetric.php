<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Metrics\AbstractMetric;
use Illuminate\Support\Facades\DB;

class TotalRevenueMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        return DB::table('orders')
            ->where('payment_status', Order::STATUS_PAID)
            ->whereBetween('created_at', [$start, $end])
            ->sum('grand_total') ?? 0.0;
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
        return __('Total Revenue');
    }
}
