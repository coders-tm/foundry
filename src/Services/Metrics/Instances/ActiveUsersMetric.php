<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Services\Metrics\AbstractMetric;
use Foundry\Services\Metrics\HandlesSubscriptionMetrics;
use Illuminate\Support\Facades\DB;

class ActiveUsersMetric extends AbstractMetric
{
    use HandlesSubscriptionMetrics;

    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $subscribers = DB::table('subscriptions')
            ->select('user_id')
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $end));

        $orderers = DB::table('orders')
            ->where('payment_status', Order::STATUS_PAID)
            ->whereBetween('created_at', [$start, $end])
            ->select('customer_id');

        return DB::table('users')
            ->whereIn('id', $subscribers)
            ->orWhereIn('id', $orderers)
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
        return __('Active Users');
    }
}
