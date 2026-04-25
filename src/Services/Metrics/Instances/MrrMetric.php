<?php

namespace Foundry\Services\Metrics\Instances;

use Carbon\Carbon;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Services\Metrics\AbstractMetric;
use Foundry\Services\Metrics\HandlesSubscriptionMetrics;
use Illuminate\Support\Facades\DB;

class MrrMetric extends AbstractMetric
{
    use HandlesSubscriptionMetrics;

    /**
     * Calculate the metric for the given date range.
     */
    public function calculate(Carbon $start, Carbon $end): mixed
    {
        return DB::table((new Foundry::$subscriptionModel)->getTable())
            ->join((new Foundry::$orderModel)->getTable(), function ($join) use ($end) {
                $join->on((new Foundry::$subscriptionModel)->getTable().'.id', '=', (new Foundry::$orderModel)->getTable().'.orderable_id')
                    ->where((new Foundry::$orderModel)->getTable().'.orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
                    ->whereIn((new Foundry::$orderModel)->getTable().'.id', function ($query) use ($end) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from((new Foundry::$orderModel)->getTable())
                            ->where('payment_status', Order::STATUS_PAID)
                            ->where('orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
                            ->where('created_at', '<=', $end)
                            ->groupBy('orderable_id');
                    });
            })
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $end, true))
            ->sum(DB::raw($this->mrrSumExpression())) ?? 0.0;
    }

    /**
     * Get the value type.
     */
    public function type(): string
    {
        return 'currency';
    }

    /**
     * Get additional metadata breakdown.
     */
    public function extra(Carbon $start, Carbon $end): array
    {
        return [
            'by_plan' => $this->calculateMrrByPlan($end),
            'by_interval' => $this->calculateMrrByInterval($end),
        ];
    }

    protected function defaultLabel(): string
    {
        return __('Monthly Recurring Revenue (MRR)');
    }

    protected function calculateMrrByPlan(Carbon $date): array
    {
        return DB::table((new Foundry::$subscriptionModel)->getTable())
            ->join('plans', (new Foundry::$subscriptionModel)->getTable().'.plan_id', '=', 'plans.id')
            ->join((new Foundry::$orderModel)->getTable(), function ($join) use ($date) {
                $join->on((new Foundry::$subscriptionModel)->getTable().'.id', '=', (new Foundry::$orderModel)->getTable().'.orderable_id')
                    ->where((new Foundry::$orderModel)->getTable().'.orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
                    ->whereIn((new Foundry::$orderModel)->getTable().'.id', function ($query) use ($date) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from((new Foundry::$orderModel)->getTable())
                            ->where('payment_status', Order::STATUS_PAID)
                            ->where('orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
                            ->where('created_at', '<=', $date)
                            ->groupBy('orderable_id');
                    });
            })
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $date, true))
            ->select('plans.label as name', DB::raw("SUM({$this->mrrSumExpression()}) as mrr"))
            ->groupBy('plans.label')
            ->get()
            ->pluck('mrr', 'name')
            ->toArray();
    }

    protected function calculateMrrByInterval(Carbon $date): array
    {
        return DB::table((new Foundry::$subscriptionModel)->getTable())
            ->join((new Foundry::$orderModel)->getTable(), function ($join) use ($date) {
                $join->on((new Foundry::$subscriptionModel)->getTable().'.id', '=', (new Foundry::$orderModel)->getTable().'.orderable_id')
                    ->where((new Foundry::$orderModel)->getTable().'.orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
                    ->whereIn((new Foundry::$orderModel)->getTable().'.id', function ($query) use ($date) {
                        $query->select(DB::raw('MAX(id)'))
                            ->from((new Foundry::$orderModel)->getTable())
                            ->where('payment_status', Order::STATUS_PAID)
                            ->where('orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
                            ->where('created_at', '<=', $date)
                            ->groupBy('orderable_id');
                    });
            })
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $date, true))
            ->select((new Foundry::$subscriptionModel)->getTable().'.billing_interval', DB::raw("SUM({$this->mrrSumExpression()}) as mrr"))
            ->groupBy((new Foundry::$subscriptionModel)->getTable().'.billing_interval')
            ->get()
            ->pluck('mrr', 'billing_interval')
            ->toArray();
    }
}
