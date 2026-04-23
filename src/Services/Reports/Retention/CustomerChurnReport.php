<?php

namespace Foundry\Services\Reports\Retention;

use Carbon\Carbon;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * Customer Churn Report - Shows customer churn rates over time.
 */
class CustomerChurnReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'period' => ['label' => 'Period', 'type' => 'text'],
        'starting_customers' => ['label' => 'Starting Customers', 'type' => 'number'],
        'churned_customers' => ['label' => 'Churned Customers', 'type' => 'number'],
        'new_customers' => ['label' => 'New Customers', 'type' => 'number'],
        'ending_customers' => ['label' => 'Ending Customers', 'type' => 'number'],
        'churn_rate' => ['label' => 'Churn Rate', 'type' => 'percentage'],
        'net_customer_change' => ['label' => 'Net Customer Change', 'type' => 'number'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'customer-churn';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Monitor customer churn rates and retention trends';
    }

    /**
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        $periods = $this->getDatePeriods();

        // Build period boundaries array
        $periodBoundaries = [];
        foreach ($periods as $periodStart) {
            $periodEnd = $this->getPeriodEnd($periodStart);
            $periodBoundaries[] = [
                'start' => $periodStart->toDateTimeString(),
                'end' => $periodEnd->toDateTimeString(),
                'order' => count($periodBoundaries),
            ];
        }

        $periodQuery = $this->buildPeriodBoundariesQuery($periodBoundaries);
        if ($periodQuery === null) {
            return $this->emptyQuery();
        }

        // Single query with all customer calculations using subqueries
        return DB::table(DB::raw("({$periodQuery->toSql()}) as periods"))
            ->mergeBindings($periodQuery)
            ->select([
                'periods.period_start',
                'periods.period_order',
                DB::raw('(
                    SELECT COUNT(DISTINCT user_id)
                    FROM subscriptions
                    WHERE created_at < periods.period_start
                    AND (canceled_at IS NULL OR canceled_at >= periods.period_start)
                ) as starting_customers'),
                DB::raw('(
                    SELECT COUNT(DISTINCT user_id)
                    FROM subscriptions
                    WHERE created_at <= periods.period_end
                    AND (canceled_at IS NULL OR canceled_at > periods.period_end)
                ) as ending_customers'),
                DB::raw('(
                    SELECT COUNT(DISTINCT new_subs.user_id)
                    FROM subscriptions as new_subs
                    WHERE new_subs.created_at BETWEEN periods.period_start AND periods.period_end
                    AND NOT EXISTS (
                        SELECT 1
                        FROM subscriptions as prior_subs
                        WHERE prior_subs.user_id = new_subs.user_id
                        AND prior_subs.created_at < periods.period_start
                    )
                ) as new_customers'),
                DB::raw('(
                    SELECT COUNT(DISTINCT churned_subs.user_id)
                    FROM subscriptions as churned_subs
                    WHERE churned_subs.canceled_at BETWEEN periods.period_start AND periods.period_end
                    AND NOT EXISTS (
                        SELECT 1
                        FROM subscriptions as active_subs
                        WHERE active_subs.user_id = churned_subs.user_id
                        AND (active_subs.canceled_at IS NULL OR active_subs.canceled_at > periods.period_end)
                    )
                ) as churned_customers'),
            ])
            ->groupBy('periods.period_start', 'periods.period_end', 'periods.period_order')
            ->orderBy('periods.period_order');
    }

    /**
     * {@inheritdoc}
     */
    public function toRow($row): array
    {
        $period = $this->formatPeriodLabel(Carbon::parse($row->period_start));

        $startingCustomers = (int) ($row->starting_customers ?? 0);
        $churnedCustomers = (int) ($row->churned_customers ?? 0);
        $newCustomers = (int) ($row->new_customers ?? 0);
        $endingCustomers = (int) ($row->ending_customers ?? 0);
        $churnRate = $startingCustomers > 0 ? ($churnedCustomers / $startingCustomers) * 100 : 0;

        return [
            'period' => $period,
            'starting_customers' => $startingCustomers,
            'churned_customers' => $churnedCustomers,
            'new_customers' => $newCustomers,
            'ending_customers' => $endingCustomers,
            'churn_rate' => (float) $churnRate,
            'net_customer_change' => $newCustomers - $churnedCustomers,
        ];
    }

    /**
     * Calculate summary statistics.
     *
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $now = now()->toDateTimeString();

        $activeCustomers = DB::table('subscriptions')
            ->whereNull('canceled_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                    ->orWhereRaw('expires_at > ?', [$now]);
            })
            ->distinct('user_id')
            ->count('user_id');

        return [
            'active_customers' => (int) $activeCustomers,
        ];
    }
}
