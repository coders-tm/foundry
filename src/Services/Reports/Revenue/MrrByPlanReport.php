<?php

namespace Foundry\Services\Reports\Revenue;

use Foundry\Models\Subscription\Plan;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * MRR by Plan Report
 *
 * Shows Monthly Recurring Revenue breakdown by plan with interval normalization.
 */
class MrrByPlanReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'plan_name' => ['label' => 'Plan Name', 'type' => 'text'],
        'plan_slug' => ['label' => 'Plan Slug', 'type' => 'text'],
        'interval' => ['label' => 'Interval', 'type' => 'text'],
        'price' => ['label' => 'Price', 'type' => 'currency'],
        'monthly_price' => ['label' => 'Monthly Price', 'type' => 'currency'],
        'active_subscriptions' => ['label' => 'Active Subscriptions', 'type' => 'number'],
        'total_quantity' => ['label' => 'Total Quantity', 'type' => 'number'],
        'mrr' => ['label' => 'MRR', 'type' => 'currency'],
        'mrr_percentage' => ['label' => 'MRR %', 'type' => 'percentage'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'mrr-by-plan';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'MRR breakdown and distribution across subscription plans';
    }

    /**
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        $now = now()->toDateTimeString();

        // Subquery 1: Aggregate subscription stats per plan
        $statsQuery = Plan::query()
            ->select([
                'plans.id',
                'plans.label',
                'plans.slug',
                'plans.interval',
                'plans.interval_count',
                'plans.price',
                'plans.is_active',
                'plans.created_at',
                'plans.updated_at',
                DB::raw("COUNT(DISTINCT CASE
                    WHEN subscriptions.canceled_at IS NULL
                    AND (subscriptions.expires_at IS NULL OR subscriptions.expires_at > '{$now}')
                    THEN subscriptions.id
                END) as active_subscriptions"),
                DB::raw("COALESCE(SUM(CASE
                    WHEN subscriptions.canceled_at IS NULL
                    AND (subscriptions.expires_at IS NULL OR subscriptions.expires_at > '{$now}')
                    THEN subscriptions.quantity
                END), 0) as total_quantity"),
            ])
            ->leftJoin('subscriptions', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('plans.is_active', true)
            ->groupBy('plans.id', 'plans.label', 'plans.slug', 'plans.interval', 'plans.interval_count', 'plans.price', 'plans.is_active', 'plans.created_at', 'plans.updated_at');

        // Subquery 2: Calculate MRR based on interval normalization
        $mrrQuery = DB::table(DB::raw("({$statsQuery->toSql()}) as plan_stats"))
            ->setBindings($statsQuery->getBindings())
            ->select([
                'plan_stats.*',
                DB::raw("CASE
                    WHEN plan_stats.interval = 'year' THEN plan_stats.price / 12
                    WHEN plan_stats.interval = 'week' THEN plan_stats.price * 4.345
                    WHEN plan_stats.interval = 'day' THEN plan_stats.price * 30
                    ELSE plan_stats.price / plan_stats.interval_count
                END as monthly_price"),
                DB::raw('CASE
                    WHEN plan_stats.total_quantity = 0 AND plan_stats.active_subscriptions > 0
                    THEN plan_stats.active_subscriptions
                    ELSE plan_stats.total_quantity
                END as effective_quantity'),
            ]);

        // Outer query: Calculate MRR and percentage using window function
        return DB::table(DB::raw("({$mrrQuery->toSql()}) as mrr_data"))
            ->mergeBindings($mrrQuery)
            ->select([
                'mrr_data.*',
                DB::raw('(mrr_data.monthly_price * mrr_data.effective_quantity) as mrr'),
                DB::raw('CASE
                    WHEN SUM(mrr_data.monthly_price * mrr_data.effective_quantity) OVER () > 0
                    THEN ((mrr_data.monthly_price * mrr_data.effective_quantity) * 100.0
                          / SUM(mrr_data.monthly_price * mrr_data.effective_quantity) OVER ())
                    ELSE 0
                END as mrr_percentage'),
            ])
            ->orderBy('mrr_data.price', 'desc');
    }

    /**
     * {@inheritdoc}
     */
    public function toRow($row): array
    {
        return [
            'plan_name' => $row->label ?? '',
            'plan_slug' => $row->slug,
            'interval' => $row->interval.($row->interval_count > 1 ? " x{$row->interval_count}" : ''),
            'price' => (float) ($row->price ?? 0),
            'monthly_price' => (float) ($row->monthly_price ?? 0),
            'active_subscriptions' => (int) ($row->active_subscriptions ?? 0),
            'total_quantity' => (int) ($row->effective_quantity ?? 0),
            'mrr' => (float) ($row->mrr ?? 0),
            'mrr_percentage' => (float) ($row->mrr_percentage ?? 0),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        // Calculate total MRR from query results
        $results = $this->query($filters)->get();

        $totalMrr = $results->sum('mrr');

        return [
            'total_mrr' => format_amount($totalMrr),
            'total_plans' => $results->count(),
        ];
    }
}
