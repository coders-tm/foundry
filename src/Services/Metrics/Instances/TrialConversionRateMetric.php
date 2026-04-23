<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Services\Metrics\AbstractMetric;
use Illuminate\Support\Facades\DB;

class TrialConversionRateMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $totalTrials = DB::table('subscriptions')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        if ($totalTrials === 0) {
            return 0.0;
        }

        $converted = DB::table('subscriptions')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('canceled_at')
                    ->orWhereColumn('canceled_at', '>', 'trial_ends_at');
            })
            ->count();

        return round(($converted / $totalTrials) * 100, 2);
    }

    /**
     * Get the value type.
     */
    public function type(): string
    {
        return 'percentage';
    }

    protected function defaultLabel(): string
    {
        return __('Trial Conversion Rate');
    }
}
