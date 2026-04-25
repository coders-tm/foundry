<?php

namespace Foundry\Services\Metrics;

use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Order;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait HandlesSubscriptionMetrics
{
    /**
     * Get MRR calculation expression (Net of Tax)
     */
    protected function mrrSumExpression(): string
    {
        return "
            CASE subscriptions.billing_interval
                WHEN 'day' THEN ((orders.grand_total - COALESCE(orders.tax_total, 0)) / COALESCE(subscriptions.billing_interval_count, 1)) * 30
                WHEN 'week' THEN ((orders.grand_total - COALESCE(orders.tax_total, 0)) / COALESCE(subscriptions.billing_interval_count, 1)) * 4.345
                WHEN 'month' THEN (orders.grand_total - COALESCE(orders.tax_total, 0)) / COALESCE(subscriptions.billing_interval_count, 1)
                WHEN 'year' THEN ((orders.grand_total - COALESCE(orders.tax_total, 0)) / COALESCE(subscriptions.billing_interval_count, 1)) / 12
                ELSE 0
            END
        ";
    }

    /**
     * Get latest paid order subquery for a specific point in time
     */
    protected function latestPaidOrderSubquery(Carbon $date): Builder
    {
        return DB::table('orders')
            ->select('orderable_id', DB::raw('MAX(created_at) as latest_created_at'))
            ->where('orderable_type', (new Foundry::$subscriptionModel)->getMorphClass())
            ->where('payment_status', Order::STATUS_PAID)
            ->where('created_at', '<=', $date)
            ->groupBy('orderable_id');
    }

    /**
     * Point-in-time check for active subscriptions
     */
    /**
     * Point-in-time check for active subscriptions with valid access.
     */
    protected function applyActiveSubscriptionFilter($query, Carbon $date, bool $payingOnly = false)
    {
        $query->where('subscriptions.created_at', '<=', $date);

        if ($payingOnly) {
            $query->where('subscriptions.is_free_forever', false);
        }

        return $query->where(function ($q) use ($date, $payingOnly) {
            if (! $payingOnly) {
                $q->where('subscriptions.is_free_forever', true);
            }

            $condition = $payingOnly ? 'where' : 'orWhere';

            $q->$condition(function ($inner) use ($date) {
                $inner->whereIn('subscriptions.status', [
                    SubscriptionStatus::ACTIVE,
                    SubscriptionStatus::TRIALING,
                    SubscriptionStatus::CANCELED,
                ])->where(function ($inner) use ($date) {
                    $inner->where(function ($q) use ($date) {
                        $q->whereNull('subscriptions.canceled_at')
                            ->where(function ($q) use ($date) {
                                $q->whereNull('subscriptions.ends_at')
                                    ->orWhere('subscriptions.ends_at', '>', $date);
                            });
                    })
                        ->orWhere('subscriptions.expires_at', '>', $date)
                        ->orWhere('subscriptions.trial_ends_at', '>', $date);
                });
            });
        });
    }

    /**
     * Get comparison description helper
     */
    protected function getComparisonDescription(array $current, array $previous, string $label): string
    {
        return __(':label from :current_start to :current_end compared with :previous_start to :previous_end', [
            'label' => $label,
            'current_start' => $current['start']->format('d M'),
            'current_end' => $current['end']->format('d M'),
            'previous_start' => $previous['start']->format('d M'),
            'previous_end' => $previous['end']->format('d M'),
        ]);
    }
}
