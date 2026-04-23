<?php

namespace Foundry\AutoRenewal\Payments;

/**
 * GoCardless payment wrapper for normalizing GoCardless payment responses.
 *
 * Provides a consistent interface for accessing payment information from GoCardless.
 */
class GoCardlessPayment extends Payment
{
    /**
     * Get the payment ID (GoCardless payment ID).
     */
    public function id(): string
    {
        return $this->data['id'] ?? '';
    }

    /**
     * Get the payment amount in the smallest currency unit.
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
     * GoCardless payment statuses: 'pending_submission', 'submitted', 'paid',
     * 'failed', 'charged_back', 'cancelled'
     */
    public function status(): string
    {
        return $this->data['status'] ?? 'unknown';
    }

    /**
     * Get the timestamp when the payment was created.
     */
    public function createdAt(): string
    {
        return $this->data['created_at'] ?? now()->toDateTimeString();
    }
}
