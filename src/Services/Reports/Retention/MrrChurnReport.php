<?php

namespace Foundry\Services\Reports\Retention;

use Carbon\Carbon;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * MRR Churn Report - Shows MRR churn rates over time.
 */
class MrrChurnReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'period' => ['label' => 'Period', 'type' => 'text'],
        'starting_mrr' => ['label' => 'Starting MRR', 'type' => 'currency'],
        'churned_mrr' => ['label' => 'Churned MRR', 'type' => 'currency'],
        'new_mrr' => ['label' => 'New MRR', 'type' => 'currency'],
        'ending_mrr' => ['label' => 'Ending MRR', 'type' => 'currency'],
        'mrr_churn_rate' => ['label' => 'MRR Churn Rate', 'type' => 'percentage'],
        'net_mrr_change' => ['label' => 'Net MRR Change', 'type' => 'currency'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'mrr-churn';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Track MRR churn and revenue impact from cancellations';
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

        // Single query with all MRR calculations using CASE statements
        return DB::table(DB::raw("({$periodQuery->toSql()}) as periods"))
            ->mergeBindings($periodQuery)
            ->leftJoin(DB::raw('subscriptions as subs_starting'), function ($join) {
                $join->whereRaw('subs_starting.created_at < periods.period_start')
                    ->whereRaw('(subs_starting.canceled_at IS NULL OR subs_starting.canceled_at >= periods.period_start)');
            })
            ->leftJoin(DB::raw('plans as plans_starting'), 'subs_starting.plan_id', '=', 'plans_starting.id')
            ->leftJoin(DB::raw('subscriptions as subs_ending'), function ($join) {
                $join->whereRaw('subs_ending.created_at <= periods.period_end')
                    ->whereRaw('(subs_ending.canceled_at IS NULL OR subs_ending.canceled_at > periods.period_end)');
            })
            ->leftJoin(DB::raw('plans as plans_ending'), 'subs_ending.plan_id', '=', 'plans_ending.id')
            ->leftJoin(DB::raw('subscriptions as subs_churned'), function ($join) {
                $join->whereRaw('subs_churned.canceled_at BETWEEN periods.period_start AND periods.period_end');
            })
            ->leftJoin(DB::raw('plans as plans_churned'), 'subs_churned.plan_id', '=', 'plans_churned.id')
            ->leftJoin(DB::raw('subscriptions as subs_new'), function ($join) {
                $join->whereRaw('subs_new.created_at BETWEEN periods.period_start AND periods.period_end');
            })
            ->leftJoin(DB::raw('plans as plans_new'), 'subs_new.plan_id', '=', 'plans_new.id')
            ->select([
                'periods.period_start',
                'periods.period_order',
                DB::raw("COALESCE(SUM(
                    CASE
                        WHEN plans_starting.interval = 'year' THEN (plans_starting.price / 12) * COALESCE(subs_starting.quantity, 1)
                        WHEN plans_starting.interval = 'week' THEN (plans_starting.price * 4.345) * COALESCE(subs_starting.quantity, 1)
                        WHEN plans_starting.interval = 'day' THEN (plans_starting.price * 30) * COALESCE(subs_starting.quantity, 1)
                        ELSE plans_starting.price * COALESCE(subs_starting.quantity, 1)
                    END
                ), 0) as starting_mrr"),
                DB::raw("COALESCE(SUM(
                    CASE
                        WHEN plans_ending.interval = 'year' THEN (plans_ending.price / 12) * COALESCE(subs_ending.quantity, 1)
                        WHEN plans_ending.interval = 'week' THEN (plans_ending.price * 4.345) * COALESCE(subs_ending.quantity, 1)
                        WHEN plans_ending.interval = 'day' THEN (plans_ending.price * 30) * COALESCE(subs_ending.quantity, 1)
                        ELSE plans_ending.price * COALESCE(subs_ending.quantity, 1)
                    END
                ), 0) as ending_mrr"),
                DB::raw("COALESCE(SUM(
                    CASE
                        WHEN plans_churned.interval = 'year' THEN (plans_churned.price / 12) * COALESCE(subs_churned.quantity, 1)
                        WHEN plans_churned.interval = 'week' THEN (plans_churned.price * 4.345) * COALESCE(subs_churned.quantity, 1)
                        WHEN plans_churned.interval = 'day' THEN (plans_churned.price * 30) * COALESCE(subs_churned.quantity, 1)
                        ELSE plans_churned.price * COALESCE(subs_churned.quantity, 1)
                    END
                ), 0) as churned_mrr"),
                DB::raw("COALESCE(SUM(
                    CASE
                        WHEN plans_new.interval = 'year' THEN (plans_new.price / 12) * COALESCE(subs_new.quantity, 1)
                        WHEN plans_new.interval = 'week' THEN (plans_new.price * 4.345) * COALESCE(subs_new.quantity, 1)
                        WHEN plans_new.interval = 'day' THEN (plans_new.price * 30) * COALESCE(subs_new.quantity, 1)
                        ELSE plans_new.price * COALESCE(subs_new.quantity, 1)
                    END
                ), 0) as new_mrr"),
            ])
            ->groupBy('periods.period_start', 'periods.period_end', 'periods.period_order')
            ->orderBy('periods.period_order');
    }

    /**
     * Define column types for frontend formatting.
     *
     * {@inheritdoc}
     */
    public function columns(): array
    {
        return [
            'period' => ['label' => 'Period', 'type' => 'text'],
            'starting_mrr' => ['label' => 'Starting MRR', 'type' => 'currency'],
            'churned_mrr' => ['label' => 'Churned MRR', 'type' => 'currency'],
            'new_mrr' => ['label' => 'New MRR', 'type' => 'currency'],
            'ending_mrr' => ['label' => 'Ending MRR', 'type' => 'currency'],
            'mrr_churn_rate' => ['label' => 'MRR Churn Rate', 'type' => 'percentage'],
            'net_mrr_change' => ['label' => 'Net MRR Change', 'type' => 'currency'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function toRow($row): array
    {
        $period = $this->formatPeriodLabel(Carbon::parse($row->period_start));

        $startingMrr = (float) ($row->starting_mrr ?? 0);
        $churnedMrr = (float) ($row->churned_mrr ?? 0);
        $newMrr = (float) ($row->new_mrr ?? 0);
        $endingMrr = (float) ($row->ending_mrr ?? 0);
        $mrrChurnRate = $startingMrr > 0 ? ($churnedMrr / $startingMrr) * 100 : 0;

        return [
            'period' => $period,
            'starting_mrr' => $startingMrr,
            'churned_mrr' => $churnedMrr,
            'new_mrr' => $newMrr,
            'ending_mrr' => $endingMrr,
            'mrr_churn_rate' => (float) $mrrChurnRate,
            'net_mrr_change' => $newMrr - $churnedMrr,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $now = now()->toDateTimeString();

        $currentMrr = DB::table('subscriptions')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->whereNull('subscriptions.canceled_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('subscriptions.expires_at')
                    ->orWhereRaw('subscriptions.expires_at > ?', [$now]);
            })
            ->selectRaw('
                COALESCE(SUM(
                    CASE plans.interval
                        WHEN \'year\' THEN plans.price / 12
                        WHEN \'week\' THEN plans.price * 4
                        WHEN \'day\' THEN plans.price * 30
                        ELSE plans.price
                    END * COALESCE(subscriptions.quantity, 1)
                ), 0) as mrr
            ')
            ->value('mrr') ?? 0;

        return [
            'current_mrr' => format_amount((float) $currentMrr),
        ];
    }
}
