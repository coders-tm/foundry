<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Services\Metrics\AbstractMetric;
use Foundry\Services\Metrics\HandlesSubscriptionMetrics;
use Illuminate\Support\Facades\DB;

class ChurnMetric extends AbstractMetric
{
    use HandlesSubscriptionMetrics;

    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $activeAtStart = DB::table('subscriptions')
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $start))
            ->distinct('subscriptions.user_id')
            ->count('subscriptions.user_id');

        $churnedDuringPeriod = DB::table('subscriptions')
            ->whereNotNull('canceled_at')
            ->whereBetween('canceled_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        return $activeAtStart > 0 ? round($churnedDuringPeriod / $activeAtStart, 4) : 0.0;
    }

    /**
     * Get the value type.
     */
    public function type(): string
    {
        return 'percentage';
    }

    /**
     * Get additional metadata breakdown.
     */
    public function extra(Carbon $start, Carbon $end): array
    {
        return [
            'logo_churn' => DB::table('subscriptions')
                ->whereNotNull('canceled_at')
                ->whereBetween('canceled_at', [$start, $end])
                ->distinct('user_id')
                ->count('user_id'),
        ];
    }

    protected function defaultLabel(): string
    {
        return __('Churn Rate');
    }
}
