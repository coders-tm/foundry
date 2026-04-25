<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Services\Metrics\AbstractMetric;
use Illuminate\Support\Facades\DB;

class NetRevenueMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $revenue = Foundry::$orderModel::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('grand_total - COALESCE(tax_total, 0)')) ?? 0.0;

        $refunds = Foundry::$orderModel::query()
            ->whereIn('payment_status', [Order::STATUS_REFUNDED])
            ->whereBetween('created_at', [$start, $end])
            ->sum('refund_total') ?? 0.0;

        return $revenue - $refunds;
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
        return __('Net Revenue');
    }
}
