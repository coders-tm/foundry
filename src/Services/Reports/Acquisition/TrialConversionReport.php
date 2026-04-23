<?php

namespace Foundry\Services\Reports\Acquisition;

use Carbon\Carbon;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * Trial Conversion Report - Shows trial to paid conversion rates.
 */
class TrialConversionReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'period' => ['label' => 'Period', 'type' => 'text'],
        'trials_started' => ['label' => 'Trials Started', 'type' => 'number'],
        'trials_expired' => ['label' => 'Trials Expired', 'type' => 'number'],
        'trials_converted' => ['label' => 'Trials Converted', 'type' => 'number'],
        'conversion_rate' => ['label' => 'Conversion Rate', 'type' => 'percentage'],
        'avg_trial_duration' => ['label' => 'Avg Trial Duration (days)', 'type' => 'number'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'trial-conversion';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Track trial-to-paid conversion rates and customer conversion patterns';
    }

    /**
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        $periods = $this->getDatePeriods();
        $now = now()->toDateTimeString();

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

        $periodQuery = $this->buildPeriodBoundariesQuery($periodBoundaries);
        if ($periodQuery === null) {
            return $this->emptyQuery();
        }

        // Database-agnostic date diff expression
        $daysExpression = $this->dbDateDiff('subscriptions.trial_ends_at', 'subscriptions.created_at');

        // Main query with aggregations
        return DB::table(DB::raw("({$periodQuery->toSql()}) as periods"))
            ->mergeBindings($periodQuery)
            ->leftJoin('subscriptions', function ($join) {
                $join->whereRaw('subscriptions.created_at >= periods.period_start')
                    ->whereRaw('subscriptions.created_at <= periods.period_end')
                    ->whereNotNull('subscriptions.trial_ends_at');
            })
            ->select([
                'periods.period_start',
                'periods.period_order',
                DB::raw('COUNT(DISTINCT subscriptions.id) as trials_started'),
                DB::raw('COUNT(DISTINCT CASE
                    WHEN subscriptions.canceled_at IS NOT NULL
                    AND subscriptions.canceled_at <= subscriptions.trial_ends_at
                    THEN subscriptions.id
                END) as trials_expired'),
                DB::raw("COUNT(DISTINCT CASE
                    WHEN (subscriptions.canceled_at IS NULL OR subscriptions.canceled_at > subscriptions.trial_ends_at)
                    AND subscriptions.trial_ends_at <= '{$now}'
                    THEN subscriptions.id
                END) as trials_converted"),
                DB::raw("AVG({$daysExpression}) as avg_trial_duration"),
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

        $trialsStarted = (int) ($row->trials_started ?? 0);
        $trialsConverted = (int) ($row->trials_converted ?? 0);
        $conversionRate = $trialsStarted > 0 ? ($trialsConverted / $trialsStarted) * 100 : 0;

        return [
            'period' => $period,
            'trials_started' => $trialsStarted,
            'trials_expired' => (int) ($row->trials_expired ?? 0),
            'trials_converted' => $trialsConverted,
            'conversion_rate' => (float) ($conversionRate),
            'avg_trial_duration' => number_format((float) ($row->avg_trial_duration ?? 0), 1),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $now = now()->toDateTimeString();

        $summary = DB::table('subscriptions')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->selectRaw('
                COUNT(*) as trials_started,
                COUNT(CASE
                    WHEN (canceled_at IS NULL OR canceled_at > trial_ends_at)
                    AND trial_ends_at <= ?
                    THEN 1
                END) as trials_converted
            ', [$now])
            ->first();

        if (! $summary) {
            return [
                'total_trials_started' => 0,
                'total_trials_converted' => 0,
                'overall_conversion_rate' => (float) 0,
            ];
        }

        $trialsStarted = (int) $summary->trials_started;
        $trialsConverted = (int) $summary->trials_converted;

        return [
            'total_trials_started' => $trialsStarted,
            'total_trials_converted' => $trialsConverted,
            'overall_conversion_rate' => $trialsStarted > 0 ? ($trialsConverted / $trialsStarted) * 100 : 0,
        ];
    }
}
