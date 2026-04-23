<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Metrics\AbstractMetric;

class OrderCountMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        return Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * Get the value type.
     */
    public function type(): string
    {
        return 'count';
    }

    protected function defaultLabel(): string
    {
        return __('Orders');
    }
}
