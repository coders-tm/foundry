<?php

namespace Foundry\AutoRenewal\Payments;

use Carbon\Carbon;

/**
 * Stripe payment wrapper for normalizing Stripe charge responses.
 *
 * Provides a consistent interface for accessing payment information from Stripe charges.
 */
class StripePayment extends Payment
{
    /**
     * Get the payment ID (Stripe charge ID).
     */
    public function id(): string
    {
        return $this->data['id'] ?? '';
    }

    /**
     * Get the payment amount in cents.
     */
    public function amount(): int
    {
        return (int) ($this->data['amount'] ?? 0);
    }

    /**
     * Get the payment currency code (uppercase).
     */
    public function currency(): string
    {
        return strtoupper($this->data['currency'] ?? '');
    }

    /**
     * Get the payment status.
     *
     * Stripe charge statuses: 'succeeded', 'failed', 'pending'
     */
    public function status(): string
    {
        $status = $this->data['status'] ?? '';

        if ($status === 'succeeded' || ($this->data['paid'] ?? false)) {
            return 'succeeded';
        }

        if (in_array($status, ['requires_action', 'requires_confirmation', 'authentication_required'])) {
            return 'requires_action';
        }

        // If it's a 3DS error, prioritize 'requires_action' over 'failed'
        $errorCode = $this->data['last_payment_error']['code'] ?? $this->data['failure_code'] ?? '';
        if ($errorCode === 'authentication_required') {
            return 'requires_action';
        }

        if ($status === 'requires_payment_method' || ! empty($errorCode)) {
            return 'failed';
        }

        if ($status === 'processing') {
            return 'pending';
        }

        return $status ?: 'pending';
    }

    /**
     * Get the timestamp when the charge was created.
     */
    public function createdAt(): string
    {
        $timestamp = $this->data['created'] ?? 0;

        return Carbon::createFromTimestamp($timestamp)->toDateTimeString();
    }
}
