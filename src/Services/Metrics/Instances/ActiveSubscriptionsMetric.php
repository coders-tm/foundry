<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Services\Metrics\AbstractMetric;
use Foundry\Services\Metrics\HandlesSubscriptionMetrics;
use Illuminate\Support\Facades\DB;

class ActiveSubscriptionsMetric extends AbstractMetric
{
    use HandlesSubscriptionMetrics;

    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        return DB::table('subscriptions')
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $end))
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
        return __('Active Subscriptions');
    }
}
