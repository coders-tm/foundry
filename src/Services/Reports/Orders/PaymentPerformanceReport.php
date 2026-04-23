<?php

namespace Foundry\Services\Reports\Orders;

use Carbon\Carbon;
use Foundry\Models\Payment;
use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * Payment Performance Report - Payment success rates and processing metrics.
 *
 * Uses Pattern 1 (Time-Series) with UNION ALL period boundaries and single query.
 * All metrics calculated in one pass without N+1 queries.
 */
class PaymentPerformanceReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'period' => ['label' => 'Period', 'type' => 'text'],
        'total_payments' => ['label' => 'Total Payments', 'type' => 'number'],
        'successful' => ['label' => 'Successful Payments', 'type' => 'number'],
        'failed' => ['label' => 'Failed Payments', 'type' => 'number'],
        'success_rate' => ['label' => 'Success Rate', 'type' => 'percentage'],
        'total_amount' => ['label' => 'Total Amount', 'type' => 'currency'],
        'total_fees' => ['label' => 'Total Fees', 'type' => 'currency'],
        'net_amount' => ['label' => 'Net Amount', 'type' => 'currency'],
        'avg_processing_time' => ['label' => 'Avg Processing Time (hours)', 'type' => 'number'],
        'refund_count' => ['label' => 'Refund Count', 'type' => 'number'],
        'refund_amount' => ['label' => 'Refund Amount', 'type' => 'currency'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'payment-performance';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Track payment success rates and processing metrics';
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

        // Database-agnostic date diff for processing time
        $processingHours = $this->dbDateDiff('payments.processed_at', 'payments.created_at');

        $completedStatus = Payment::STATUS_COMPLETED;
        $failedStatus = Payment::STATUS_FAILED;

        // Single query with all metrics
        return DB::table(DB::raw("({$periodQuery->toSql()}) as periods"))
            ->mergeBindings($periodQuery)
            ->leftJoin(DB::raw('payments'), function ($join) {
                $join->whereRaw('payments.created_at BETWEEN periods.period_start AND periods.period_end');
            })
            ->select([
                'periods.period_start',
                'periods.period_order',
                DB::raw('COUNT(payments.id) as total_payments'),
                DB::raw("COUNT(CASE WHEN payments.status = '{$completedStatus}' THEN 1 END) as successful"),
                DB::raw("COUNT(CASE WHEN payments.status = '{$failedStatus}' THEN 1 END) as failed"),
                DB::raw('COALESCE(SUM(payments.amount), 0) as total_amount'),
                DB::raw('COALESCE(SUM(payments.fees), 0) as total_fees'),
                DB::raw('COALESCE(SUM(payments.net_amount), 0) as net_amount'),
                DB::raw('COALESCE(SUM(payments.refund_amount), 0) as refund_amount'),
                DB::raw('COUNT(CASE WHEN payments.refund_amount > 0 THEN 1 END) as refund_count'),
                DB::raw("COALESCE(AVG(CASE WHEN payments.processed_at IS NOT NULL THEN ({$processingHours}) * 24 END), 0) as avg_processing_time"),
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

        $totalPayments = (int) ($row->total_payments ?? 0);
        $successful = (int) ($row->successful ?? 0);

        $successRate = $totalPayments > 0
            ? ($successful / $totalPayments) * 100
            : 0;

        return [
            'period' => $period,
            'total_payments' => $totalPayments,
            'successful' => $successful,
            'failed' => (int) ($row->failed ?? 0),
            'success_rate' => (float) $successRate,
            'total_amount' => (float) ($row->total_amount ?? 0),
            'total_fees' => (float) ($row->total_fees ?? 0),
            'net_amount' => (float) ($row->net_amount ?? 0),
            'avg_processing_time' => (float) ($row->avg_processing_time ?? 0),
            'refund_count' => (int) ($row->refund_count ?? 0),
            'refund_amount' => (float) ($row->refund_amount ?? 0),
        ];
    }

    /**
     * Calculate summary statistics.
     *
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $stats = DB::table('payments')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->selectRaw('
                COUNT(*) as total_payments,
                COUNT(CASE WHEN status = ? THEN 1 END) as successful,
                COUNT(CASE WHEN status = ? THEN 1 END) as failed,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(fees), 0) as total_fees,
                COALESCE(SUM(refund_amount), 0) as refund_amount
            ', [Payment::STATUS_COMPLETED, Payment::STATUS_FAILED])
            ->first();

        if (! $stats) {
            return [
                'total_payments' => 0,
                'successful' => 0,
                'failed' => 0,
                'success_rate' => (float) 0,
                'total_amount' => format_amount(0),
                'total_fees' => format_amount(0),
                'refund_amount' => format_amount(0),
            ];
        }

        $totalPayments = (int) $stats->total_payments;
        $successful = (int) $stats->successful;

        return [
            'total_payments' => $totalPayments,
            'successful' => $successful,
            'failed' => (int) $stats->failed,
            'success_rate' => $totalPayments > 0 ? ($successful / $totalPayments) * 100 : 0,
            'total_amount' => format_amount($stats->total_amount),
            'total_fees' => format_amount($stats->total_fees),
            'refund_amount' => format_amount($stats->refund_amount),
        ];
    }
}
