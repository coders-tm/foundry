<?php

namespace Foundry\Services\Metrics;

use Carbon\Carbon;
use Foundry\Foundry;
use Illuminate\Support\Facades\DB;

class SubscriptionMetrics extends MetricsCalculator
{
    use HandlesSubscriptionMetrics;

    protected string $cachePrefix = 'subscription_metrics';

    /**
     * Get active subscriptions as of end of range
     */
    public function getActiveCount(): int
    {
        return $this->remember('active_count', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $range['end']))
                ->count();
        });
    }

    /**
     * Get subscriptions on grace period (at-risk)
     */
    public function getGracePeriodCount(): int
    {
        return $this->remember('grace_period', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->whereNotNull('canceled_at')
                ->where('expires_at', '>', $range['end'])
                ->where('canceled_at', '<=', $range['end'])
                ->count();
        });
    }

    /**
     * Get cancelled subscriptions for date range
     */
    public function getCancelledCount(): int
    {
        return $this->remember('cancelled_count', function () {
            $range = $this->getDateRange();

            return $this->cancelledBetween($range['start'], $range['end']);
        });
    }

    /**
     * Get trial subscriptions
     */
    public function getTrialCount(): int
    {
        return $this->remember('trial_count', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', $range['end'])
                ->where('created_at', '<=', $range['end'])
                ->count();
        });
    }

    /**
     * Get churn rate (percentage)
     */
    public function getChurnRate(): float
    {
        return $this->remember('churn_rate', function () {
            $range = $this->getDateRange();

            return $this->churnRateBetween($range['start'], $range['end']);
        });
    }

    /**
     * Get new subscriptions for date range
     */
    public function getNewSubscriptions(): int
    {
        return $this->remember('new_subscriptions', function () {
            $range = $this->getDateRange();

            return $this->newSubscriptionsBetween($range['start'], $range['end']);
        });
    }

    /**
     * Get new subscriptions this month
     */
    public function getNewThisMonth(): int
    {
        return $this->remember('new_this_month', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->whereMonth('created_at', $range['end']->month)
                ->whereYear('created_at', $range['end']->year)
                ->count();
        });
    }

    /**
     * Get trial conversion rate
     */
    public function getTrialConversionRate(): float
    {
        return $this->remember('trial_conversion', function () {
            $range = $this->getDateRange();

            return $this->trialConversionBetween($range['start'], $range['end']);
        });
    }

    /**
     * Get subscriptions by plan
     */
    public function getByPlan(): array
    {
        return $this->remember('by_plan', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->select('plans.label as plan_name', 'plans.id as plan_id', DB::raw('count(*) as count'))
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $range['end']))
                ->groupBy('plans.id', 'plans.label')
                ->orderByDesc('count')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get subscriptions by billing interval
     */
    public function getByInterval(): array
    {
        return $this->remember('by_interval', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->select(
                    'billing_interval',
                    'billing_interval_count',
                    DB::raw('COUNT(*) as count')
                )
                ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $range['end']))
                ->groupBy('billing_interval', 'billing_interval_count')
                ->orderBy('billing_interval')
                ->orderBy('billing_interval_count')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get subscriptions by status
     */
    public function getByStatus(): array
    {
        return $this->remember('by_status', function () {
            return Foundry::$subscriptionModel::query()
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray();
        });
    }

    /**
     * Get average subscription lifetime in days
     */
    public function getAverageLifetime(): float
    {
        return $this->remember('avg_lifetime', function () {
            // Use database-agnostic approach
            $subscriptions = Foundry::$subscriptionModel::query()
                ->whereNotNull('canceled_at')
                ->get(['created_at', 'canceled_at']);

            if ($subscriptions->isEmpty()) {
                return 0.0;
            }

            $totalDays = $subscriptions->sum(function ($sub) {
                return $sub->created_at->diffInDays($sub->canceled_at);
            });

            return round($totalDays / $subscriptions->count(), 2);
        });
    }

    /**
     * Get retention rate (percentage)
     */
    public function getRetentionRate(): float
    {
        return $this->remember('retention_rate', function () {
            $range = $this->getDateRange();

            $startingSubscriptions = Foundry::$subscriptionModel::query()
                ->where('created_at', '<=', $range['start'])
                ->count();

            if ($startingSubscriptions === 0) {
                return 0.0;
            }

            $retained = Foundry::$subscriptionModel::query()
                ->where('created_at', '<=', $range['start'])
                ->where(function ($q) use ($range) {
                    $q->whereNull('canceled_at')
                        ->orWhere('canceled_at', '>', $range['end']);
                })
                ->count();

            return round(($retained / $startingSubscriptions) * 100, 2);
        });
    }

    /**
     * Get frozen subscriptions count
     */
    public function getFrozenCount(): int
    {
        return $this->remember('frozen_count', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->whereNotNull('frozen_at')
                ->where('frozen_at', '<=', $range['end'])
                ->where(function ($q) use ($range) {
                    $q->whereNull('release_at')
                        ->orWhere('release_at', '>', $range['end']);
                })
                ->count();
        });
    }

    /**
     * Get subscriptions pending release from freeze
     */
    public function getPendingReleaseCount(): int
    {
        return $this->remember('pending_release', function () {
            return Foundry::$subscriptionModel::query()
                ->whereNotNull('frozen_at')
                ->whereNotNull('release_at')
                ->where('release_at', '<=', now()->addDays(7))
                ->where('release_at', '>', now())
                ->count();
        });
    }

    /**
     * Get subscriptions with active contracts
     */
    public function getContractCount(): int
    {
        return $this->remember('contract_count', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->whereNotNull('total_cycles')
                ->where('total_cycles', '>', 0)
                ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $range['end']))
                ->count();
        });
    }

    /**
     * Get subscriptions with contracts ending in next 30 days
     */
    public function getContractsEndingSoon(): int
    {
        return $this->remember('contracts_ending_soon', function () {
            $range = $this->getDateRange();
            $next30Days = $range['end']->copy()->addDays(30);

            return Foundry::$subscriptionModel::query()
                ->whereNotNull('total_cycles')
                ->where('total_cycles', '>', 0)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$range['end'], $next30Days])
                ->count();
        });
    }

    /**
     * Get renewal forecast for next 30 days
     */
    public function getRenewalForecast(): array
    {
        return $this->remember('renewal_forecast', function () {
            $range = $this->getDateRange();
            $next30Days = $range['end']->copy()->addDays(30);
            $latestOrders = $this->latestPaidOrderSubquery($range['end']);

            $subscriptions = Foundry::$subscriptionModel::query()
                ->joinSub($latestOrders, 'latest_orders', function ($join) {
                    $join->on('subscriptions.id', '=', 'latest_orders.orderable_id');
                })
                ->join('orders', function ($join) {
                    $join->on('orders.orderable_id', '=', 'subscriptions.id')
                        ->on('orders.created_at', '=', 'latest_orders.latest_created_at')
                        ->where('orders.orderable_type', '=', (new Foundry::$subscriptionModel)->getMorphClass());
                })
                ->select(
                    DB::raw('DATE(subscriptions.expires_at) as renewal_date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw("SUM({$this->mrrSumExpression()}) as expected_mrr")
                )
                ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $range['end']))
                ->whereNotNull('subscriptions.expires_at')
                ->whereBetween('subscriptions.expires_at', [$range['end'], $next30Days])
                ->groupBy(DB::raw('DATE(subscriptions.expires_at)'))
                ->orderBy('renewal_date')
                ->get()
                ->toArray();

            return [
                'renewals' => $subscriptions,
                'total_count' => array_sum(array_column($subscriptions, 'count')),
                'expected_mrr' => round(array_sum(array_column($subscriptions, 'expected_mrr')), 2),
            ];
        });
    }

    /**
     * Get plan upgrade/downgrade metrics
     */
    public function getPlanChangeMetrics(): array
    {
        return $this->remember('plan_changes', function () {
            $range = $this->getDateRange();

            // Subscriptions with scheduled downgrades
            $scheduledDowngrades = Foundry::$subscriptionModel::query()
                ->where('is_downgrade', true)
                ->whereNotNull('next_plan')
                ->count();

            // Assuming plan changes are tracked via subscription history or logs
            // This is a placeholder - would need actual plan change tracking
            $upgrades = 0;
            $downgrades = 0;

            return [
                'scheduled_downgrades' => $scheduledDowngrades,
                'upgrades' => $upgrades,
                'downgrades' => $downgrades,
            ];
        });
    }

    /**
     * Get subscriptions expiring today
     */
    public function getExpiringTodayCount(): int
    {
        return $this->remember('expiring_today', function () {
            $range = $this->getDateRange();

            return Foundry::$subscriptionModel::query()
                ->whereDate('expires_at', $range['end']->toDateString())
                ->count();
        });
    }

    /**
     * Get subscription growth rate (percentage)
     */
    public function getGrowthRate(): float
    {
        return $this->remember('growth_rate', function () {
            $range = $this->getDateRange();
            $currentMonth = Foundry::$subscriptionModel::query()
                ->whereMonth('created_at', $range['end']->month)
                ->whereYear('created_at', $range['end']->year)
                ->count();

            $previousMonthEnd = $range['end']->copy()->subMonth();
            $previousMonth = Foundry::$subscriptionModel::query()
                ->whereMonth('created_at', $previousMonthEnd->month)
                ->whereYear('created_at', $previousMonthEnd->year)
                ->count();

            if ($previousMonth === 0) {
                return $currentMonth > 0 ? 100.0 : 0.0;
            }

            return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 2);
        });
    }

    /**
     * Get all metrics with period comparison
     */
    public function get(): array
    {
        return $this->only([
            'active_count',
            'grace_period_count',
            'cancelled_count',
            'trial_count',
            'churn_rate',
            'new_subscriptions',
            'new_this_month',
            'trial_conversion_rate',
            'average_lifetime_days',
            'retention_rate',
            'growth_rate',
            'frozen_count',
            'pending_release_count',
            'contract_count',
            'contracts_ending_soon',
            'renewal_forecast',
            'plan_changes',
            'expiring_today',
        ]);
    }

    /**
     * Get specific metrics by keys
     */
    public function only(array $keys): array
    {
        $periods = $this->getComparisonPeriods();
        $availableKpis = $this->getAvailableKpis($periods);

        $payload = [];
        foreach ($keys as $key) {
            if (isset($availableKpis[$key])) {
                $payload[$key] = $availableKpis[$key]();
            }
        }

        return $this->withComparisons($payload, $this->getComparisonDefinitions($periods));
    }

    protected function getAvailableKpis(array $periods): array
    {
        return [
            'active_count' => fn () => $this->getActiveCount(),
            'grace_period_count' => fn () => $this->getGracePeriodCount(),
            'cancelled_count' => fn () => $this->getCancelledCount(),
            'trial_count' => fn () => $this->getTrialCount(),
            'churn_rate' => fn () => $this->getChurnRate(),
            'new_subscriptions' => fn () => $this->getNewSubscriptions(),
            'new_this_month' => fn () => $this->getNewThisMonth(),
            'trial_conversion_rate' => fn () => $this->getTrialConversionRate(),
            'average_lifetime_days' => fn () => $this->getAverageLifetime(),
            'retention_rate' => fn () => $this->getRetentionRate(),
            'growth_rate' => fn () => $this->getGrowthRate(),
            'frozen_count' => fn () => $this->getFrozenCount(),
            'pending_release_count' => fn () => $this->getPendingReleaseCount(),
            'contract_count' => fn () => $this->getContractCount(),
            'contracts_ending_soon' => fn () => $this->getContractsEndingSoon(),
            'renewal_forecast' => fn () => $this->getRenewalForecast(),
            'plan_changes' => fn () => $this->getPlanChangeMetrics(),
            'expiring_today' => fn () => $this->getExpiringTodayCount(),
            'by_plan' => fn () => $this->getByPlan(),
            'by_interval' => fn () => $this->getByInterval(),
            'by_status' => fn () => $this->getByStatus(),
        ];
    }

    protected function getComparisonDefinitions(array $periods): array
    {
        return [
            'cancelled_count' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->cancelledBetween($start, $end),
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('Cancellations')),
            ],
            'new_subscriptions' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->newSubscriptionsBetween($start, $end),
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('New subscriptions')),
            ],
            'churn_rate' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->churnRateBetween($start, $end),
                'type' => 'percentage',
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('Churn rate')),
            ],
            'trial_conversion_rate' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->trialConversionBetween($start, $end),
                'type' => 'percentage',
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('Trial conversion')),
            ],
        ];
    }

    protected function cancelledBetween(Carbon $start, Carbon $end): int
    {
        return Foundry::$subscriptionModel::query()
            ->whereNotNull('canceled_at')
            ->whereBetween('canceled_at', [$start, $end])
            ->count();
    }

    protected function newSubscriptionsBetween(Carbon $start, Carbon $end): int
    {
        return Foundry::$subscriptionModel::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    protected function churnRateBetween(Carbon $start, Carbon $end): float
    {
        $activeStart = Foundry::$subscriptionModel::query()
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $start))
            ->count();

        if ($activeStart === 0) {
            return 0.0;
        }

        $churned = $this->cancelledBetween($start, $end);

        return round(($churned / $activeStart) * 100, 2);
    }

    protected function trialConversionBetween(Carbon $start, Carbon $end): float
    {
        $totalTrials = Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        if ($totalTrials === 0) {
            return 0.0;
        }

        $converted = Subscription::query()
            ->whereNotNull('trial_ends_at')
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('canceled_at')
                    ->orWhere('canceled_at', '>', DB::raw('trial_ends_at'));
            })
            ->count();

        return round(($converted / $totalTrials) * 100, 2);
    }
}
