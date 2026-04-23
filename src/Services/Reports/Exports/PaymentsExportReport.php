<?php

namespace Foundry\Services\Reports\Exports;

use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * Payments Export Report
 *
 * Exports payment transaction history with method details and financial tracking.
 * Net amount = amount - fees. Polymorphic paymentable links to orders/invoices/subscriptions.
 */
class PaymentsExportReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'id' => ['label' => 'Payment ID', 'type' => 'text'],
        'transaction_id' => ['label' => 'Transaction ID', 'type' => 'text'],
        'amount' => ['label' => 'Amount', 'type' => 'currency'],
        'currency' => ['label' => 'Currency', 'type' => 'text'],
        'fees' => ['label' => 'Fees', 'type' => 'currency'],
        'net_amount' => ['label' => 'Net Amount', 'type' => 'currency'],
        'refund_amount' => ['label' => 'Refund Amount', 'type' => 'currency'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'payment_method' => ['label' => 'Payment Method', 'type' => 'text'],
        'paymentable_type' => ['label' => 'Paymentable Type', 'type' => 'text'],
        'paymentable_id' => ['label' => 'Paymentable ID', 'type' => 'number'],
        'processed_at' => ['label' => 'Processed At', 'type' => 'text'],
        'created_at' => ['label' => 'Created At', 'type' => 'text'],
        'note' => ['label' => 'Note', 'type' => 'text'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'payments';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Export payment transaction history';
    }

    /**
     * Build the base query with payment method JOIN.
     *
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        return DB::table('payments')
            ->leftJoin('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->select([
                'payments.id',
                'payments.transaction_id',
                'payments.amount',
                'payments.currency',
                'payments.fees',
                'payments.net_amount',
                'payments.refund_amount',
                'payments.status',
                'payments.paymentable_type',
                'payments.paymentable_id',
                'payments.processed_at',
                'payments.created_at',
                'payments.note',
                DB::raw('COALESCE(payment_methods.name, payment_methods.provider, "") as payment_method_name'),
            ])
            ->orderBy('payments.created_at', 'desc');
    }

    /**
     * Transform row to array with raw values.
     *
     * No additional queries - payment method already joined.
     *
     * {@inheritdoc}
     */
    public function toRow($row): array
    {
        return [
            'id' => (int) $row->id,
            'transaction_id' => $row->transaction_id ?? '',
            'amount' => (float) ($row->amount ?? 0),
            'currency' => $row->currency ?? 'USD',
            'fees' => (float) ($row->fees ?? 0),
            'net_amount' => (float) ($row->net_amount ?? 0),
            'refund_amount' => (float) ($row->refund_amount ?? 0),
            'status' => $row->status ?? '',
            'payment_method' => $row->payment_method_name ?? '',
            'paymentable_type' => $row->paymentable_type ?? '',
            'paymentable_id' => $row->paymentable_id ? (int) $row->paymentable_id : null,
            'processed_at' => $row->processed_at ?? '',
            'created_at' => $row->created_at ?? '',
            'note' => $row->note ?? '',
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
            ->select([DB::raw('COUNT(*) as total_payments')])
            ->first();

        return [
            'total_payments' => (int) $stats->total_payments,
        ];
    }
}
