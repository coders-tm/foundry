<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Services\Metrics\AbstractMetric;
use Illuminate\Support\Facades\DB;

class TotalUsersMetric extends AbstractMetric
{
    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        return DB::table('users')
            ->where('created_at', '<=', $end)
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
        return __('Total Users');
    }
}
