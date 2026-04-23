<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Services\Metrics\AbstractMetric;
use Foundry\Services\Metrics\HandlesSubscriptionMetrics;
use Illuminate\Support\Facades\DB;

class ArpuMetric extends AbstractMetric
{
    use HandlesSubscriptionMetrics;

    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $revenue = DB::table('subscriptions')
            ->join('orders', function ($join) use ($end) {
                $join->on('subscriptions.id', '=', 'orders.orderable_id')
                    ->where('orders.orderable_type', (new Subscription)->getMorphClass())
                    ->whereIn('orders.id', function ($query) use ($end) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from('orders')
                            ->where('payment_status', Order::STATUS_PAID)
                            ->where('orderable_type', (new Subscription)->getMorphClass())
                            ->where('created_at', '<=', $end)
                            ->groupBy('orderable_id');
                    });
            })
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $end, true))
            ->sum(DB::raw($this->mrrSumExpression())) ?? 0.0;

        $activeUsers = DB::table('subscriptions')
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $end))
            ->distinct('subscriptions.user_id')
            ->count('subscriptions.user_id');

        return $activeUsers > 0 ? round($revenue / $activeUsers, 2) : 0.0;
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
        return __('ARPU');
    }
}
