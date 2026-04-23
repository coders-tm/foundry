<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Services\Metrics\AbstractMetric;
use Foundry\Services\Metrics\HandlesSubscriptionMetrics;
use Illuminate\Support\Facades\DB;

class RevenueChurnMetric extends AbstractMetric
{
    use HandlesSubscriptionMetrics;

    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        $previousMrr = DB::table('subscriptions')
            ->join('orders', function ($join) use ($start) {
                $join->on('subscriptions.id', '=', 'orders.orderable_id')
                    ->where('orders.orderable_type', (new Subscription)->getMorphClass())
                    ->whereIn('orders.id', function ($query) use ($start) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from('orders')
                            ->where('payment_status', Order::STATUS_PAID)
                            ->where('orderable_type', (new Subscription)->getMorphClass())
                            ->where('created_at', '<=', $start)
                            ->groupBy('orderable_id');
                    });
            })
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $start, true))
            ->sum(DB::raw($this->mrrSumExpression())) ?? 0.0;

        $churnedMrr = DB::table('subscriptions')
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
            ->whereNotNull('subscriptions.canceled_at')
            ->whereBetween('subscriptions.canceled_at', [$start, $end])
            ->where('subscriptions.is_free_forever', false)
            ->sum(DB::raw($this->mrrSumExpression())) ?? 0.0;

        return $previousMrr > 0 ? round($churnedMrr / $previousMrr, 4) : 0.0;
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
        return __('Revenue Churn');
    }

    public function extra(Carbon $start, Carbon $end): array
    {
        $churnedMrr = DB::table('subscriptions')
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
            ->whereNotNull('subscriptions.canceled_at')
            ->whereBetween('subscriptions.canceled_at', [$start, $end])
            ->sum(DB::raw($this->mrrSumExpression())) ?? 0.0;

        return [
            'lost_mrr' => $churnedMrr,
        ];
    }
}
