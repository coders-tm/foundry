<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;

class PaystackPayment extends AbstractPayment
{
    /**
     * Create from Paystack transaction or response
     *
     * @param  object|array  $response  Paystack transaction or payment response
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

        $transactionData = $response['data'] ?? $response;
        $this->transactionId = $transactionData['reference'] ?? uniqid('paystack_');

        // Store amount in BASE currency
        // Paystack amount is in kobo, convert back to decimal
        $this->amount = ($transactionData['amount'] ?? 0) / 100;
        $this->currency = strtoupper($transactionData['currency'] ?? config('app.currency', 'USD'));

        // Map Paystack statuses
        $this->status = match ($response['status'] ?? 'unknown') {
            'success', 'successful' => Payment::STATUS_COMPLETED,
            'pending', 'ongoing' => Payment::STATUS_PROCESSING,
            'failed', 'abandoned' => Payment::STATUS_FAILED,
            'cancelled' => Payment::STATUS_CANCELLED,
            default => Payment::STATUS_FAILED,
        };

        $this->note = "Paystack Transaction: {$this->transactionId} (Status: {$this->status})";
        if (isset($response['gateway_response'])) {
            $this->note .= " - {$response['gateway_response']}";
        }

        $this->processedAt = new DateTime;
        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Extract standardized payment method metadata from Paystack response
     */
    protected function extractMetadata($data): array
    {
        // Ensure array format
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $normalized = [];

        // Get transaction data
        $transactionData = $data['data'] ?? $data;

        // Extract channel
        $channel = $transactionData['channel'] ?? null;
        $normalized['payment_method_type'] = $channel;

        // Card authorization details
        if (isset($transactionData['authorization'])) {
            $auth = $transactionData['authorization'];
            $normalized['card_brand'] = ucfirst($auth['card_type'] ?? 'card');
            $normalized['last_four'] = $auth['last4'] ?? null;
            $normalized['bank_name'] = $auth['bank'] ?? null;
            $normalized['country'] = $auth['country_code'] ?? null;

            // Reusable authorization
            if (isset($auth['reusable'])) {
                $normalized['reusable'] = $auth['reusable'];
            }
        }

        // Mobile money details
        if ($channel === 'mobile_money' && isset($transactionData['authorization']['mobile_money_number'])) {
            $normalized['wallet_type'] = 'mobile_money';
            $normalized['mobile_number'] = $transactionData['authorization']['mobile_money_number'];
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
        $channel = $metadata['payment_method_type'] ?? null;

        // Card payment
        if ($channel === 'card' && isset($metadata['card_brand']) && isset($metadata['last_four'])) {
            $display = "{$metadata['card_brand']} •••• {$metadata['last_four']}";
            if (isset($metadata['bank_name'])) {
                $display .= " ({$metadata['bank_name']})";
            }

            return $display;
        }

        // Mobile money
        if ($channel === 'mobile_money') {
            if (isset($metadata['mobile_number'])) {
                return "Mobile Money ({$metadata['mobile_number']})";
            }

            return 'Mobile Money';
        }

        // USSD
        if ($channel === 'ussd') {
            return 'USSD'.(isset($metadata['bank_name']) ? " ({$metadata['bank_name']})" : '');
        }

        // Bank transfer
        if ($channel === 'bank_transfer') {
            return 'Bank Transfer'.(isset($metadata['bank_name']) ? " ({$metadata['bank_name']})" : '');
        }

        // Bank (direct debit)
        if ($channel === 'bank') {
            return 'Bank'.(isset($metadata['bank_name']) ? " ({$metadata['bank_name']})" : '');
        }

        // QR code
        if ($channel === 'qr') {
            return 'QR Code';
        }

        // Unknown channel
        if ($channel) {
            return ucfirst(str_replace('_', ' ', $channel));
        }

        return 'Paystack';
    }
}
