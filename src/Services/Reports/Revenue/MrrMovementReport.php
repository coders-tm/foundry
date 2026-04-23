<?php

namespace Foundry\Services\Reports\Revenue;

use Carbon\Carbon;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * MRR Movement Report - Shows MRR changes over time (new, expansion, churn, contraction).
 */
class MrrMovementReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'period' => ['label' => 'Period', 'type' => 'text'],
        'starting_mrr' => ['label' => 'Starting MRR', 'type' => 'currency'],
        'new_mrr' => ['label' => 'New MRR', 'type' => 'currency'],
        'expansion_mrr' => ['label' => 'Expansion MRR', 'type' => 'currency'],
        'churned_mrr' => ['label' => 'Churned MRR', 'type' => 'currency'],
        'contraction_mrr' => ['label' => 'Contraction MRR', 'type' => 'currency'],
        'net_mrr_change' => ['label' => 'Net MRR Change', 'type' => 'currency'],
        'ending_mrr' => ['label' => 'Ending MRR', 'type' => 'currency'],
        'mrr_growth_rate' => ['label' => 'MRR Growth Rate', 'type' => 'percentage'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'mrr-movement';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Analyze MRR changes from new, expansion, churn, and contraction';
    }

    /**
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        $periods = $this->getDatePeriods();

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

        // Use database-agnostic group concatenation
        $concatExpr = $this->isSQLite()
            ? "(subscriptions.id || ':' || subscriptions.plan_id || ':' || COALESCE(subscriptions.quantity, 1))"
            : 'CONCAT(subscriptions.id, ":", subscriptions.plan_id, ":", COALESCE(subscriptions.quantity, 1))';

        // Build aggregations for new and churned subscriptions
        $newGroupConcat = $this->dbGroupConcat(
            "CASE
                WHEN subscriptions.created_at >= periods.period_start
                AND subscriptions.created_at <= periods.period_end
                THEN {$concatExpr}
            END",
            '|',
            ! $this->isSQLite() // Use DISTINCT for non-SQLite databases
        );

        $churnedGroupConcat = $this->dbGroupConcat(
            "CASE
                WHEN subscriptions.canceled_at >= periods.period_start
                AND subscriptions.canceled_at <= periods.period_end
                THEN {$concatExpr}
            END",
            '|',
            ! $this->isSQLite() // Use DISTINCT for non-SQLite databases
        );

        return DB::table(DB::raw("({$periodQuery->toSql()}) as periods"))
            ->mergeBindings($periodQuery)
            ->leftJoin('subscriptions', function ($join) {
                $join->where(function ($q) {
                    $q->whereBetween('subscriptions.created_at', [DB::raw('periods.period_start'), DB::raw('periods.period_end')])
                        ->orWhereBetween('subscriptions.canceled_at', [DB::raw('periods.period_start'), DB::raw('periods.period_end')]);
                });
            })
            ->select([
                'periods.period_start',
                'periods.period_end',
                'periods.period_order',
                DB::raw("{$newGroupConcat} as new_subscription_data"),
                DB::raw("{$churnedGroupConcat} as churned_subscription_data"),
            ])
            ->groupBy('periods.period_start', 'periods.period_end', 'periods.period_order')
            ->orderBy('periods.period_order');
    }

    /**
     * {@inheritdoc}
     */
    public function toRow($row): array
    {
        // Compute period label from period_start timestamp
        $periodStart = Carbon::parse($row->period_start);
        $period = $this->formatPeriodLabel($periodStart);

        // Calculate starting MRR from database (independent of previous rows)
        $startingMrr = $this->calculateMrrAtDate($periodStart->copy()->subDay());

        // Cache all plans for MRR calculation (keep as Plan objects, not arrays)
        static $plans = null;
        if ($plans === null) {
            $plans = Plan::all()->keyBy('id');
        }

        // Calculate new MRR from aggregated data
        $newMrr = 0;
        if (! empty($row->new_subscription_data)) {
            $data = explode('|', $row->new_subscription_data);
            // Handle SQLite duplicates
            if ($this->isSQLite()) {
                $data = array_unique($data);
            }
            foreach ($data as $item) {
                if (empty($item)) {
                    continue;
                }
                [$subId, $planId, $quantity] = explode(':', $item);
                $plan = $plans[$planId] ?? null;
                if ($plan) {
                    $newMrr += $this->getMonthlyPrice($plan) * (int) $quantity;
                }
            }
        }

        // Calculate churned MRR from aggregated data
        $churnedMrr = 0;
        if (! empty($row->churned_subscription_data)) {
            $data = explode('|', $row->churned_subscription_data);
            // Handle SQLite duplicates
            if ($this->isSQLite()) {
                $data = array_unique($data);
            }
            foreach ($data as $item) {
                if (empty($item)) {
                    continue;
                }
                [$subId, $planId, $quantity] = explode(':', $item);
                $plan = $plans[$planId] ?? null;
                if ($plan) {
                    $churnedMrr += $this->getMonthlyPrice($plan) * (int) $quantity;
                }
            }
        }

        // For now, expansion and contraction are simplified
        $expansionMrr = 0;
        $contractionMrr = 0;

        $netMrrChange = $newMrr + $expansionMrr - $churnedMrr - $contractionMrr;
        $endingMrr = $startingMrr + $netMrrChange;

        $mrrGrowthRate = $startingMrr > 0
            ? (($endingMrr - $startingMrr) / $startingMrr) * 100
            : 0;

        return [
            'period' => $period,
            'starting_mrr' => (float) $startingMrr,
            'new_mrr' => (float) $newMrr,
            'expansion_mrr' => (float) $expansionMrr,
            'churned_mrr' => (float) $churnedMrr,
            'contraction_mrr' => (float) $contractionMrr,
            'net_mrr_change' => (float) $netMrrChange,
            'ending_mrr' => (float) $endingMrr,
            'mrr_growth_rate' => (float) $mrrGrowthRate,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $currentMrr = $this->calculateMrrAtDate(now());

        return [
            'current_mrr' => format_amount((float) $currentMrr),
        ];
    }
}
