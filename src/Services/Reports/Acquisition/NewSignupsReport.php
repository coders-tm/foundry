<?php

namespace Foundry\Services\Reports\Acquisition;

use Carbon\Carbon;
use Foundry\Foundry;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * New Signups Report - Shows new user acquisition metrics.
 */
class NewSignupsReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'period' => ['label' => 'Period', 'type' => 'text'],
        'new_users' => ['label' => 'New Users', 'type' => 'number'],
        'new_subscriptions' => ['label' => 'New Subscriptions', 'type' => 'number'],
        'trial_signups' => ['label' => 'Trial Signups', 'type' => 'number'],
        'paid_signups' => ['label' => 'Paid Signups', 'type' => 'number'],
        'mrr_added' => ['label' => 'MRR Added', 'type' => 'currency'],
        'top_plan' => ['label' => 'Top Plan', 'type' => 'text'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'new-signups';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Analyze new user acquisition metrics and signup trends';
    }

    /**
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        $periods = $this->getDatePeriods();

        // Build period boundaries array
        $periodBoundaries = [];
        foreach ($periods as $index => $periodStart) {
            $periodEnd = $this->getPeriodEnd($periodStart);
            $periodBoundaries[] = [
                'start' => $periodStart->toDateTimeString(),
                'end' => $periodEnd->toDateTimeString(),
                'order' => $index,
            ];
        }

        // Build UNION ALL query using trait helper
        $periodQuery = $this->buildPeriodBoundariesQuery($periodBoundaries);
        if ($periodQuery === null) {
            return $this->emptyQuery();
        }

        $userTable = (new (Foundry::$userModel))->getTable();

        // Main query with aggregations
        return DB::table(DB::raw("({$periodQuery->toSql()}) as periods"))
            ->mergeBindings($periodQuery)
            ->leftJoin($userTable, function ($join) use ($userTable) {
                $join->whereRaw("{$userTable}.created_at >= periods.period_start")
                    ->whereRaw("{$userTable}.created_at <= periods.period_end");
            })
            ->leftJoin('subscriptions', function ($join) {
                $join->whereRaw('subscriptions.created_at >= periods.period_start')
                    ->whereRaw('subscriptions.created_at <= periods.period_end');
            })
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->select([
                'periods.period_start',
                'periods.period_end',
                'periods.period_order',
                DB::raw("COUNT(DISTINCT {$userTable}.id) as new_users"),
                DB::raw('COUNT(DISTINCT subscriptions.id) as new_subscriptions'),
                DB::raw('COUNT(DISTINCT CASE WHEN subscriptions.trial_ends_at IS NOT NULL THEN subscriptions.id END) as trial_signups'),
                DB::raw('COUNT(DISTINCT CASE WHEN subscriptions.trial_ends_at IS NULL THEN subscriptions.id END) as paid_signups'),
                DB::raw('COALESCE(SUM(DISTINCT CASE WHEN subscriptions.canceled_at IS NULL THEN plans.price ELSE 0 END), 0) as mrr_added'),
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

        return [
            'period' => $period,
            'new_users' => (int) ($row->new_users ?? 0),
            'new_subscriptions' => (int) ($row->new_subscriptions ?? 0),
            'trial_signups' => (int) ($row->trial_signups ?? 0),
            'paid_signups' => (int) ($row->paid_signups ?? 0),
            'mrr_added' => $this->money($row->mrr_added ?? 0),
            'top_plan' => $row->top_plan ?? 'N/A',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function stream(array $filters, callable $consume): void
    {
        $query = $this->query($filters);

        if (isset($filters['limit']) && $filters['limit'] > 0) {
            $query->limit($filters['limit']);
        }

        foreach ($query->cursor() as $row) {
            // Get top plan for this period using period_start and period_end
            $topPlan = DB::table('subscriptions')
                ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
                ->whereRaw('subscriptions.created_at >= ?', [$row->period_start])
                ->whereRaw('subscriptions.created_at <= ?', [$row->period_end])
                ->select('plans.label', DB::raw('COUNT(*) as count'))
                ->groupBy('plans.id', 'plans.label')
                ->orderByDesc('count')
                ->value('label') ?? 'N/A';

            $row->top_plan = $topPlan;
            $consume($this->toRow($row));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $userModel = Foundry::$userModel;

        $summary = DB::table((new $userModel)->getTable())
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->selectRaw('COUNT(*) as total_new_users')
            ->first();

        $subSummary = DB::table('subscriptions')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->whereBetween('subscriptions.created_at', [$filters['from'], $filters['to']])
            ->selectRaw('
                COUNT(*) as total_new_subscriptions,
                COALESCE(SUM(CASE WHEN subscriptions.canceled_at IS NULL THEN plans.price END), 0) as total_mrr_added
            ')
            ->first();

        return [
            'total_new_users' => (int) ($summary->total_new_users ?? 0),
            'total_new_subscriptions' => (int) ($subSummary->total_new_subscriptions ?? 0),
            'total_mrr_added' => (float) ($subSummary->total_mrr_added ?? 0),
        ];
    }
}
