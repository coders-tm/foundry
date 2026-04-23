<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Services\Metrics\AbstractMetric;
use Illuminate\Support\Facades\DB;

class CancelledSubscriptionsMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        return DB::table('subscriptions')
            ->whereNotNull('canceled_at')
            ->whereBetween('canceled_at', [$start, $end])
            ->count();
    }

    /**
     * Get the value type.
     */
    public function type(): string
    {
        return 'number';
    }

    protected function defaultLabel(): string
    {
        return __('Cancelled Subscriptions');
    }
}
