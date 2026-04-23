<?php

namespace Foundry\Services\Reports\Orders;

use Carbon\Carbon;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * Tax Summary Report - Tax collection by region/rate.
 *
 * Uses Pattern 1 (Time-Series) with UNION ALL period boundaries and single query.
 * All metrics calculated in one pass without N+1 queries.
 */
class TaxSummaryReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'period' => ['label' => 'Period', 'type' => 'text'],
        'total_orders' => ['label' => 'Total Orders', 'type' => 'number'],
        'taxable_orders' => ['label' => 'Taxable Orders', 'type' => 'number'],
        'tax_total' => ['label' => 'Tax Total', 'type' => 'currency'],
        'taxable_amount' => ['label' => 'Taxable Amount', 'type' => 'currency'],
        'avg_tax_rate' => ['label' => 'Avg Tax Rate', 'type' => 'percentage'],
        'tax_exempt_orders' => ['label' => 'Tax Exempt Orders', 'type' => 'number'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'tax-summary';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Track tax collection and compliance by jurisdiction';
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

        // Single query with all metrics
        return DB::table(DB::raw("({$periodQuery->toSql()}) as periods"))
            ->mergeBindings($periodQuery)
            ->leftJoin(DB::raw('orders'), function ($join) {
                $join->whereRaw('orders.created_at BETWEEN periods.period_start AND periods.period_end');
            })
            ->select([
                'periods.period_start',
                'periods.period_order',
                DB::raw('COUNT(orders.id) as total_orders'),
                DB::raw('COUNT(CASE WHEN orders.tax_total > 0 THEN 1 END) as taxable_orders'),
                DB::raw('COALESCE(SUM(orders.tax_total), 0) as tax_total'),
                DB::raw('COALESCE(SUM(orders.sub_total), 0) as taxable_amount'),
                DB::raw('COUNT(CASE WHEN orders.tax_total = 0 OR orders.tax_total IS NULL THEN 1 END) as tax_exempt_orders'),
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
        $period = $this->formatPeriodLabel(Carbon::parse($row->period_start));

        $taxableAmount = (float) ($row->taxable_amount ?? 0);
        $taxTotal = (float) ($row->tax_total ?? 0);

        $avgTaxRate = $taxableAmount > 0
            ? ($taxTotal / $taxableAmount) * 100
            : 0;

        return [
            'period' => $period,
            'total_orders' => (int) ($row->total_orders ?? 0),
            'taxable_orders' => (int) ($row->taxable_orders ?? 0),
            'tax_total' => $taxTotal,
            'taxable_amount' => $taxableAmount,
            'avg_tax_rate' => (float) $avgTaxRate,
            'tax_exempt_orders' => (int) ($row->tax_exempt_orders ?? 0),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $stats = DB::table('orders')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->selectRaw('
                COUNT(*) as total_orders,
                COUNT(CASE WHEN tax_total > 0 THEN 1 END) as taxable_orders,
                COALESCE(SUM(tax_total), 0) as tax_total,
                COALESCE(SUM(sub_total), 0) as taxable_amount
            ')
            ->first();

        if (! $stats) {
            return [
                'total_orders' => 0,
                'taxable_orders' => 0,
                'tax_total' => (float) 0,
                'avg_tax_rate' => (float) 0,
            ];
        }

        $taxableAmount = (float) $stats->taxable_amount;
        $taxTotal = (float) $stats->tax_total;

        return [
            'total_orders' => (int) $stats->total_orders,
            'taxable_orders' => (int) $stats->taxable_orders,
            'tax_total' => $taxTotal,
            'avg_tax_rate' => $taxableAmount > 0 ? ($taxTotal / $taxableAmount) * 100 : 0,
        ];
    }
}
