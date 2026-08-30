<?php

namespace Foundry\Mandate\Payments;

use Carbon\Carbon;

/**
 * Paddle payment wrapper for normalizing Paddle transaction responses.
 *
 * Provides a consistent interface for accessing payment information from Paddle transactions.
 */
class PaddlePayment extends Payment
{
    /**
     * Get the payment ID (Paddle transaction ID).
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
        return strtoupper($this->data['currency'] ?? $this->data['currency_code'] ?? 'USD');
    }

    /**
     * Get the payment status.
     *
     * Paddle transaction statuses: 'completed', 'paid', 'ready', 'billed', 'processing', 'canceled', 'failed'
     */
    public function status(): string
    {
        $status = strtolower($this->data['status'] ?? '');

        if (in_array($status, ['completed', 'paid', 'succeeded'])) {
            return 'succeeded';
        }

        if (in_array($status, ['requires_action', 'requires_auth', 'authentication_required'])) {
            return 'requires_action';
        }

        if (in_array($status, ['canceled', 'cancelled', 'failed'])) {
            return 'failed';
        }

        if (in_array($status, ['ready', 'billed', 'processing', 'draft'])) {
            return 'pending';
        }

        return $status ?: 'pending';
    }

    /**
     * Get the timestamp when the transaction was created.
     */
    public function createdAt(): string
    {
        $timestamp = $this->data['created_at'] ?? $this->data['created'] ?? null;

        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((int) $timestamp)->toDateTimeString();
        }

        if (is_string($timestamp) && ! empty($timestamp)) {
            return Carbon::parse($timestamp)->toDateTimeString();
        }

        return now()->toDateTimeString();
    }
}
