<?php

namespace Foundry\AutoRenewal\Payments;

use Carbon\Carbon;

/**
 * PayPal payment wrapper for normalizing PayPal transaction responses.
 *
 * Provides a consistent interface for accessing payment information from PayPal transactions.
 */
class PaypalPayment extends Payment
{
    /**
     * Get the payment ID (PayPal Transaction/Capture ID).
     */
    public function id(): string
    {
        return $this->data['id'] ?? '';
    }

    /**
     * Get the payment amount in cents/smallest unit.
     */
    public function amount(): int
    {
        // PayPal returns amount as string with decimal (e.g. "10.00")
        $amount = $this->data['purchase_units'][0]['payments']['captures'][0]['amount']['value']
            ?? $this->data['amount']['value']
            ?? 0;

        return (int) (floatval($amount) * 100);
    }

    /**
     * Get the payment currency code (uppercase).
     */
    public function currency(): string
    {
        return strtoupper(
            $this->data['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code']
            ?? $this->data['amount']['currency_code']
            ?? ''
        );
    }

    /**
     * Get the payment status.
     *
     * PayPal transaction statuses: 'COMPLETED', 'PENDING', 'FAILED', 'VOIDED'
     */
    public function status(): string
    {
        $status = strtoupper($this->data['status'] ?? '');

        if ($status === 'COMPLETED') {
            return 'succeeded';
        }

        if ($status === 'PENDING') {
            return 'pending';
        }

        if (in_array($status, ['FAILED', 'DENIED', 'EXPIRED'])) {
            return 'failed';
        }

        return strtolower($status) ?: 'pending';
    }

    /**
     * Get the timestamp when the transaction was created.
     */
    public function createdAt(): string
    {
        $time = $this->data['create_time'] ?? $this->data['update_time'] ?? now();

        return Carbon::parse($time)->toDateTimeString();
    }
}
