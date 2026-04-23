<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;

class KlarnaPayment extends AbstractPayment
{
    /**
     * Create from Klarna session or order response
     *
     * @param  object|array  $response  Klarna session or order response
     * @param  PaymentMethod  $paymentMethod  Payment method (required)
     */
    public function __construct($response, PaymentMethod $paymentMethod)
    {
        // Convert to array if object
        if (is_object($response)) {
            $response = json_decode(json_encode($response), true);
        }

        // Set payment method
        $this->paymentMethod = $paymentMethod;

        // Determine transaction ID (session_id or order_id)
        $this->transactionId = $response['order_id'] ?? $response['session_id'] ?? uniqid('klarna_');

        // Store amount and currency from response
        // Klarna amount is in cents, convert back to decimal
        $this->amount = ($response['order_amount'] ?? 0) / 100;
        $this->currency = strtoupper($response['purchase_currency'] ?? config('app.currency', 'USD'));

        // Determine status based on session or order
        if (isset($response['session_id'])) {
            // Session status
            $this->status = match ($response['status'] ?? 'incomplete') {
                'complete' => Payment::STATUS_COMPLETED,
                'incomplete' => Payment::STATUS_PENDING,
                'error' => Payment::STATUS_FAILED,
                default => Payment::STATUS_PENDING,
            };
            $this->note = "Klarna Session: {$this->transactionId}";
        } else {
            // Order status - check fraud_status
            $fraudStatus = $response['fraud_status'] ?? 'AUTHORIZED';
            $this->status = $fraudStatus === 'REJECTED'
                ? Payment::STATUS_FAILED
                : Payment::STATUS_PROCESSING;
            $this->note = "Klarna Order: {$this->transactionId} - Fraud Status: {$fraudStatus}";
        }

        $this->processedAt = new DateTime;
        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Extract standardized payment method metadata from Klarna response
     */
    protected function extractMetadata($data): array
    {
        // Ensure array format
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $normalized = [
            'payment_method_type' => 'klarna',
        ];

        // Payment method category
        if (isset($data['payment_method_category'])) {
            $normalized['klarna_category'] = $data['payment_method_category'];
        }

        // Billing address
        if (isset($data['billing_address'])) {
            $address = $data['billing_address'];
            $normalized['country'] = $address['country'] ?? null;
        }

        // Customer information
        if (isset($data['customer'])) {
            $normalized['customer_email'] = $data['customer']['email'] ?? null;
        }

        // Build display string
        $normalized['payment_method'] = $this->buildDisplayString($normalized);

        return array_filter($normalized);
    }

    /**
     * Build human-readable payment method display string
     */
    private function buildDisplayString(array $metadata): string
    {
        $category = $metadata['klarna_category'] ?? null;

        if ($category) {
            $displayNames = [
                'pay_later' => 'Klarna Pay Later',
                'pay_over_time' => 'Klarna Financing',
                'pay_now' => 'Klarna Pay Now',
                'direct_bank_transfer' => 'Klarna Bank Transfer',
                'direct_debit' => 'Klarna Direct Debit',
            ];

            return $displayNames[$category] ?? 'Klarna - '.ucwords(str_replace('_', ' ', $category));
        }

        return 'Klarna';
    }
}
