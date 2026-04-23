<?php

namespace Foundry\Services\Metrics;

use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Illuminate\Support\Facades\DB;

class CustomerMetrics extends MetricsCalculator
{
    use HandlesSubscriptionMetrics;

    protected string $cachePrefix = 'customer_metrics';

    /**
     * Get total customers count
     */
    public function getTotalCount(): int
    {
        return $this->remember('total_count', function () {
            return Foundry::$userModel::count();
        });
    }

    /**
     * Get new customers for date range
     */
    public function getNewCustomers(): int
    {
        return $this->remember('new_customers', function () {
            $range = $this->getDateRange();

            return Foundry::$userModel::query()
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->count();
        });
    }

    /**
     * Get customer growth rate (percentage)
     */
    public function getGrowthRate(): float
    {
        return $this->remember('growth_rate', function () {
            $periods = $this->getComparisonPeriods();

            $currentCount = $this->newCustomersBetween($periods['current']['start'], $periods['current']['end']);
            $previousCount = $this->newCustomersBetween($periods['previous']['start'], $periods['previous']['end']);

            if ($previousCount === 0) {
                return $currentCount > 0 ? 100.0 : 0.0;
            }

            return round((($currentCount - $previousCount) / $previousCount) * 100, 2);
        });
    }

    /**
     * Get customers with active subscriptions
     */
    public function getActiveSubscribers(): int
    {
        return $this->remember('active_subscribers', function () {
            $range = $this->getDateRange();

            return Subscription::query()
                ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $range['end']))
                ->distinct('user_id')
                ->count('user_id');
        });
    }

    /**
     * Get subscription adoption rate (percentage)
     */
    public function getSubscriptionAdoptionRate(): float
    {
        return $this->remember('subscription_adoption_rate', function () {
            $totalCustomers = $this->getTotalCount();

            if ($totalCustomers === 0) {
                return 0.0;
            }

            $subscribers = $this->getActiveSubscribers();

            return round(($subscribers / $totalCustomers) * 100, 2);
        });
    }

    /**
     * Get top customers by revenue
     */
    public function getTopByRevenue(int $limit = 10): array
    {
        return $this->remember("top_by_revenue_{$limit}", function () use ($limit) {
            $range = $this->getDateRange();

            return Foundry::$userModel::query()
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->selectRaw('SUM(orders.grand_total - COALESCE(orders.tax_total, 0)) as total_spent')
                ->join('orders', 'users.id', '=', 'orders.customer_id')
                ->where('orders.payment_status', 'paid')
                ->whereBetween('orders.created_at', [$range['start'], $range['end']])
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->orderByDesc('total_spent')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get repeat purchase rate
     */
    public function getRepeatPurchaseRate(): float
    {
        return $this->remember('repeat_purchase_rate', function () {
            $range = $this->getDateRange();

            $customersWithOrders = DB::table('orders')
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->distinct('customer_id')
                ->count('customer_id');

            if ($customersWithOrders === 0) {
                return 0.0;
            }

            $repeatCustomers = DB::table('orders')
                ->select('customer_id')
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$range['start'], $range['end']])
                ->groupBy('customer_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();

            return round(($repeatCustomers / $customersWithOrders) * 100, 2);
        });
    }

    /**
     * Get average customer value (ARPU)
     */
    public function getAverageValue(): float
    {
        return $this->remember('average_value', function () {
            $range = $this->getDateRange();

            return $this->averageValueBetween($range['start'], $range['end']);
        });
    }

    /**
     * Get Customer Lifetime Value (LTV)
     * LTV = ARPU / Churn Rate
     */
    public function getCLV(): float
    {
        return $this->remember('clv', function () {
            $range = $this->getDateRange();
            $arpu = $this->getAverageValue();
            $churnRate = $this->getChurnRate();

            if ($churnRate <= 0) {
                return 0.0;
            }

            return round($arpu / ($churnRate / 100), 2);
        });
    }

    /**
     * Get historic customer value (total revenue / total customers)
     */
    public function getHistoricCLV(): float
    {
        return $this->remember('historic_clv', function () {
            $avgRevenue = DB::table('users')
                ->select(DB::raw('AVG(total_revenue) as avg_revenue'))
                ->fromSub(function ($query) {
                    $query->from('users')
                        ->select('users.id')
                        ->selectRaw('COALESCE(SUM(orders.grand_total - COALESCE(orders.tax_total, 0)), 0) as total_revenue')
                        ->leftJoin('orders', function ($join) {
                            $join->on('users.id', '=', 'orders.customer_id')
                                ->where('orders.payment_status', 'paid');
                        })
                        ->groupBy('users.id');
                }, 'user_revenues')
                ->value('avg_revenue');

            return round($avgRevenue ?? 0, 2);
        });
    }

    /**
     * Get customer segments based on spending
     */
    public function getSegments(): array
    {
        return $this->remember('segments', function () {

            // Get customers with order totals
            $segments = DB::table('users')
                ->select(
                    DB::raw('CASE
                        WHEN total_spent >= 1000 THEN "high_value"
                        WHEN total_spent >= 100 THEN "medium_value"
                        WHEN total_spent > 0 THEN "low_value"
                        ELSE "no_purchase"
                    END as segment'),
                    DB::raw('COUNT(*) as count')
                )
                ->fromSub(function ($query) {
                    $query->from('users')
                        ->select('users.id')
                        ->selectRaw('COALESCE(SUM(orders.grand_total - COALESCE(orders.tax_total, 0)), 0) as total_spent')
                        ->leftJoin('orders', function ($join) {
                            $join->on('users.id', '=', 'orders.customer_id')
                                ->where('orders.payment_status', 'paid');
                        })
                        ->groupBy('users.id');
                }, 'customer_totals')
                ->groupBy('segment')
                ->get()
                ->pluck('count', 'segment')
                ->toArray();

            return array_merge([
                'high_value' => 0,
                'medium_value' => 0,
                'low_value' => 0,
                'no_purchase' => 0,
            ], $segments);
        });
    }

    /**
     * Get customer churn rate (percentage)
     */
    public function getChurnRate(): float
    {
        return $this->remember('churn_rate', function () {
            $range = $this->getDateRange();

            return $this->churnRateBetween($range['start'], $range['end']);
        });
    }

    /**
     * Get customers at risk (grace period or payment issues)
     */
    public function getAtRiskCount(): int
    {
        return $this->remember('at_risk_count', function () {
            $range = $this->getDateRange();

            return $this->atRiskAtDate($range['end']);
        });
    }

    /**
     * Get all metrics
     */
    public function get(): array
    {
        $payload = [
            'total_count' => $this->getTotalCount(),
            'new_customers' => $this->getNewCustomers(),
            'growth_rate' => $this->getGrowthRate(),
            'active_subscribers' => $this->getActiveSubscribers(),
            'subscription_adoption_rate' => $this->getSubscriptionAdoptionRate(),
            'top_by_revenue' => $this->getTopByRevenue(),
            'repeat_purchase_rate' => $this->getRepeatPurchaseRate(),
            'arpu' => $this->getAverageValue(),
            'ltv' => $this->getCLV(),
            'churn_rate' => $this->getChurnRate(),
            'segments' => $this->getSegments(),
            'at_risk_count' => $this->getAtRiskCount(),
            'metadata' => $this->getMetadata(),
        ];

        $periods = $this->getComparisonPeriods();

        return $this->withComparisons($payload, [
            'new_customers' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->newCustomersBetween($start, $end),
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('New customers')),
            ],
            'repeat_purchase_rate' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->repeatRateBetween($start, $end),
                'type' => 'percentage',
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('Repeat purchase rate')),
            ],
            'arpu' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->averageValueBetween($start, $end),
                'type' => 'currency',
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('ARPU')),
            ],
            'churn_rate' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->churnRateBetween($start, $end),
                'type' => 'percentage',
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('Churn rate')),
            ],
            'at_risk_count' => [
                'calculator' => fn (Carbon $start, Carbon $end) => $this->atRiskAtDate($end),
                'description' => $this->getComparisonDescription($periods['current'], $periods['previous'], __('At-risk customers')),
            ],
        ]);
    }

    protected function newCustomersBetween(Carbon $start, Carbon $end): int
    {

        return Foundry::$userModel::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    protected function repeatRateBetween(Carbon $start, Carbon $end): float
    {
        $customersWithOrders = DB::table('orders')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->distinct('customer_id')
            ->count('customer_id');

        if ($customersWithOrders === 0) {
            return 0.0;
        }

        $repeatCustomers = DB::table('orders')
            ->select('customer_id')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return round(($repeatCustomers / $customersWithOrders) * 100, 2);
    }

    protected function averageValueBetween(Carbon $start, Carbon $end): float
    {
        $totalRevenue = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('grand_total - COALESCE(tax_total, 0)')) ?? 0.0;

        $totalUsers = Foundry::$userModel::query()
            ->where('created_at', '<=', $end)
            ->count();

        return $totalUsers > 0 ? round($totalRevenue / $totalUsers, 2) : 0.0;
    }

    protected function churnRateBetween(Carbon $start, Carbon $end): float
    {
        $activeStart = Subscription::query()
            ->when(true, fn ($q) => $this->applyActiveSubscriptionFilter($q, $start))
            ->count();

        if ($activeStart === 0) {
            return 0.0;
        }

        $churned = Subscription::query()
            ->whereBetween('canceled_at', [$start, $end])
            ->count();

        return round(($churned / $activeStart) * 100, 2);
    }

    protected function atRiskAtDate(Carbon $date): int
    {
        return Subscription::query()
            ->where(function ($query) use ($date) {
                // Grace Period: Canceled but active
                $query->where(function ($q) use ($date) {
                    $q->whereNotNull('canceled_at')
                        ->where('expires_at', '>', $date)
                        ->where('canceled_at', '<=', $date);
                })
                // Payment Issues: Incomplete, Expired (Past Due), or Pending
                    ->orWhereIn('status', [
                        SubscriptionStatus::INCOMPLETE,
                        SubscriptionStatus::EXPIRED,
                        SubscriptionStatus::PENDING,
                    ]);
            })
            ->distinct('user_id')
            ->count('user_id');
    }
}
