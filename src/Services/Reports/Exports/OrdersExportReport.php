<?php

namespace Foundry\Services\Reports\Exports;

use Foundry\Services\Reports\AbstractReport;

/**
 * Orders Export Report
 *
 * Exports individual order records with comprehensive financial and status tracking.
 * Includes status (draft/pending/completed), payment status, fulfillment status, and totals.
 */
class OrdersExportReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'id' => ['label' => 'Order ID', 'type' => 'text'],
        'customer_email' => ['label' => 'Customer Email', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'payment_status' => ['label' => 'Payment Status', 'type' => 'text'],
        'fulfillment_status' => ['label' => 'Fulfillment Status', 'type' => 'text'],
        'grand_total' => ['label' => 'Grand Total', 'type' => 'currency'],
        'sub_total' => ['label' => 'Sub Total', 'type' => 'currency'],
        'tax_total' => ['label' => 'Tax Total', 'type' => 'currency'],
        'discount_total' => ['label' => 'Discount Total', 'type' => 'currency'],
        'shipping_total' => ['label' => 'Shipping Total', 'type' => 'currency'],
        'refund_total' => ['label' => 'Refund Total', 'type' => 'currency'],
        'paid_total' => ['label' => 'Paid Total', 'type' => 'currency'],
        'created_at' => ['label' => 'Created At', 'type' => 'text'],
        'completed_at' => ['label' => 'Completed At', 'type' => 'text'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'orders';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Export order data with detailed information';
    }

    /**
     * Build the base query with all order fields.
     *
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        return $this->buildOrdersQuery()
            ->orderBy('created_at', 'desc');
    }

    /**
     * Transform row to array with raw values.
     *
     * {@inheritdoc}
     */
    public function toRow($row): array
    {
        return [
            'id' => (int) $row->id,
            'customer_email' => $row->customer_id ?? '',
            'status' => $row->status ?? '',
            'payment_status' => $row->payment_status ?? '',
            'fulfillment_status' => $row->fulfillment_status ?? '',
            'grand_total' => (float) ($row->grand_total ?? 0),
            'sub_total' => (float) ($row->sub_total ?? 0),
            'tax_total' => (float) ($row->tax_total ?? 0),
            'discount_total' => (float) ($row->discount_total ?? 0),
            'shipping_total' => (float) ($row->shipping_total ?? 0),
            'refund_total' => (float) ($row->refund_total ?? 0),
            'paid_total' => (float) ($row->paid_total ?? 0),
            'created_at' => $row->created_at ?? '',
            'completed_at' => $row->completed_at ?? '',
        ];
    }

    /**
     * Calculate summary statistics.
     *
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $query = $this->buildOrdersQuery();

        return [
            'total_orders' => (int) $query->count(),
        ];
    }
}
