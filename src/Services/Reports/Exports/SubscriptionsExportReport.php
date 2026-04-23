<?php

namespace Foundry\Services\Reports\Exports;

use Foundry\Services\Reports\AbstractReport;
use Illuminate\Support\Facades\DB;

/**
 * Subscriptions Export Report
 *
 * Exports subscription lifecycle data with plan details via LEFT JOIN.
 * Grace period logic: canceled_at set but expires_at future = still active access.
 */
class SubscriptionsExportReport extends AbstractReport
{
    /**
     * {@inheritdoc}
     */
    protected array $columns = [
        'id' => ['label' => 'ID', 'type' => 'text'],
        'user_id' => ['label' => 'User ID', 'type' => 'number'],
        'plan' => ['label' => 'Plan', 'type' => 'text'],
        'status' => ['label' => 'Status', 'type' => 'text'],
        'price' => ['label' => 'Price', 'type' => 'currency'],
        'interval' => ['label' => 'Interval', 'type' => 'text'],
        'quantity' => ['label' => 'Quantity', 'type' => 'number'],
        'trial_ends_at' => ['label' => 'Trial Ends At', 'type' => 'text'],
        'expires_at' => ['label' => 'Expires At', 'type' => 'text'],
        'canceled_at' => ['label' => 'Canceled At', 'type' => 'text'],
        'created_at' => ['label' => 'Created At', 'type' => 'text'],
        'updated_at' => ['label' => 'Updated At', 'type' => 'text'],
    ];

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'subscriptions';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Export subscription data with filtering options';
    }

    /**
     * Build the base query with plan JOIN.
     *
     * {@inheritdoc}
     */
    public function query(array $filters)
    {
        return DB::table('subscriptions')
            ->leftJoin('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->select([
                'subscriptions.id',
                'subscriptions.user_id',
                'subscriptions.type',
                'subscriptions.status',
                'subscriptions.quantity',
                'subscriptions.trial_ends_at',
                'subscriptions.expires_at',
                'subscriptions.canceled_at',
                'subscriptions.created_at',
                'subscriptions.updated_at',
                DB::raw('COALESCE(plans.price, 0) as plan_price'),
                'plans.interval as plan_interval',
            ])
            ->orderBy('subscriptions.created_at', 'desc');
    }

    /**
     * Transform row to array with raw values.
     *
     * No additional queries - plan already joined.
     *
     * {@inheritdoc}
     */
    public function toRow($row): array
    {
        return [
            'id' => (int) $row->id,
            'user_id' => (int) ($row->user_id ?? 0),
            'plan' => $row->type ?? '',
            'status' => $row->status ?? '',
            'price' => (float) ($row->plan_price ?? 0),
            'interval' => $row->plan_interval ?? '',
            'quantity' => (int) ($row->quantity ?? 1),
            'trial_ends_at' => $row->trial_ends_at ?? '',
            'expires_at' => $row->expires_at ?? '',
            'canceled_at' => $row->canceled_at ?? '',
            'created_at' => $row->created_at ?? '',
            'updated_at' => $row->updated_at ?? '',
        ];
    }

    /**
     * Calculate summary statistics.
     *
     * {@inheritdoc}
     */
    public function summarize(array $filters): array
    {
        $stats = DB::table('subscriptions')
            ->select([DB::raw('COUNT(*) as total_subscriptions')])
            ->first();

        return [
            'total_subscriptions' => (int) $stats->total_subscriptions,
        ];
    }
}
