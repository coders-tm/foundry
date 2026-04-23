<?php

namespace Foundry\Services\Subscription;

use Carbon\Carbon;
use Foundry\Foundry;
use Foundry\Models\Subscription;

class SubscriptionStatusManager
{
    /**
     * @var Subscription
     */
    protected $subscription;

    /**
     * Create a new status manager instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Prepare a DateTime instance for array/JSON serialization.
     *
     * @param  \DateTimeInterface|null  $date
     * @return string|null
     */
    protected function formatDate($date)
    {
        if ($date instanceof \DateTimeInterface) {
            return Carbon::instance($date)
                ->setTimezone(config('app.timezone', 'UTC'))
                ->format(Foundry::$dateTimeFormat);
        }

        return $date;
    }

    /**
     * Get subscription status with detailed information
     */
    public function toResponse(array $extends = []): array
    {
        $status = [
            'id' => $this->subscription->id,
            'status' => $this->subscription->status,
            'active' => $this->subscription->active(),
            'canceled' => $this->subscription->canceled(),
            'ended' => $this->subscription->ended(),
            'expired' => $this->subscription->expired(),
            'downgrade' => $this->subscription->hasDowngrade(),
            'on_grace_period' => $this->subscription->onGracePeriod(),
            'canceled_on_grace_period' => $this->subscription->canceledOnGracePeriod(),
            'has_incomplete_payment' => $this->subscription->hasIncompletePayment(),
            'has_due' => $this->subscription->onGracePeriod() || $this->subscription->expired() || $this->subscription->hasIncompletePayment(),
            'on_trial' => $this->subscription->onTrial(),
            'is_valid' => $this->subscription->valid(),
            'type' => $this->subscription->type,
            'is_downgrade' => $this->subscription->is_downgrade,
            'is_free_forever' => $this->subscription->is_free_forever,
            'next_plan' => $this->subscription->next_plan,
            'trial_ends_at' => $this->formatDate($this->subscription->trial_ends_at),
            'expires_at' => $this->formatDate($this->subscription->expires_at),
            'ends_at' => $this->formatDate($this->subscription->ends_at),
            'starts_at' => $this->formatDate($this->subscription->starts_at),
            'canceled_at' => $this->formatDate($this->subscription->canceled_at),
            'frozen_at' => $this->formatDate($this->subscription->frozen_at),
            'release_at' => $this->formatDate($this->subscription->release_at),
            'provider' => $this->subscription->provider,
            'metadata' => $this->subscription->metadata ?? [],
            'billing_interval' => $this->subscription->billing_interval,
            'billing_interval_count' => $this->subscription->billing_interval_count,
            'total_cycles' => $this->subscription->total_cycles,
            'current_cycle' => $this->subscription->current_cycle,
            'created_at' => $this->formatDate($this->subscription->created_at),
            'updated_at' => $this->formatDate($this->subscription->updated_at),
            'invoice' => null,
        ];

        // Get upcoming invoice if exists
        try {
            $upcomingInvoice = $this->subscription->upcomingInvoice();
        } catch (\Throwable $e) {
            $upcomingInvoice = null;
        }

        // Add coupon details
        if ($this->subscription->coupon_id && $this->subscription->coupon) {
            $status['coupon'] = $this->subscription->coupon->toPublic();
        }

        // Set message and invoice details based on status
        if ($this->subscription->onGracePeriod() ||
            $this->subscription->expired() ||
            $this->subscription->hasIncompletePayment()) {

            $invoice = $this->subscription->latestInvoice ?? $upcomingInvoice;
            if ($invoice) {
                $status['invoice'] = [
                    'id' => $invoice->id,
                    'amount' => $invoice->total(),
                    'date' => $this->subscription->expires_at?->format('d M, Y'),
                    'sub_total' => $invoice->sub_total,
                    'discount_total' => $invoice->discount_total,
                    'tax_total' => $invoice->tax_total,
                    'grand_total' => $invoice->grand_total,
                ];
            }
        } elseif ($upcomingInvoice) {
            $status['invoice'] = [
                'amount' => $upcomingInvoice->total(),
                'date' => $this->subscription->expires_at?->format('d M, Y'),
                'sub_total' => $upcomingInvoice->sub_total,
                'discount_total' => $upcomingInvoice->discount_total,
                'tax_total' => $upcomingInvoice->tax_total,
                'grand_total' => $upcomingInvoice->grand_total,
            ];
        }

        if (in_array('plan', $extends)) {
            $status['plan'] = $this->subscription->plan;
        }

        if (in_array('user', $extends)) {
            $status['user'] = $this->subscription->user;
        }

        if (in_array('next_plan', $extends) && $this->subscription->hasDowngrade()) {
            $status['next_plan'] = $this->subscription->next_plan;
        }

        if (in_array('usages', $extends)) {
            $status['usages'] = $this->subscription->usagesToArray();
        }

        return $status;
    }
}
