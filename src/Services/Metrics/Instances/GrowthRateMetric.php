<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Services\Metrics\AbstractMetric;
use Illuminate\Support\Facades\DB;

class GrowthRateMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     * This metric specifically handles its own comparison for growth rate.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $currentCount = DB::table('users')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $periodDays = $start->diffInDays($end);
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($periodDays);

        $previousCount = DB::table('users')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        if ($previousCount === 0) {
            return $currentCount > 0 ? 100.0 : 0.0;
        }

        return round((($currentCount - $previousCount) / $previousCount) * 100, 2);
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
        return __('Customer Growth Rate');
    }
}
