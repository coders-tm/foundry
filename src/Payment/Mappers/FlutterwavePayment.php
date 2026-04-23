<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;

class FlutterwavePayment extends AbstractPayment
{
    /**
     * Create from Flutterwave transaction response
     *
     * @param  object|array  $response  Flutterwave transaction response
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

        $this->transactionId = $response['tx_ref'] ?? $response['id'] ?? uniqid('flw_');

        // Store amount and currency from response
        $this->amount = $response['amount'] ?? 0;
        $this->currency = strtoupper($response['currency'] ?? config('app.currency', 'USD'));

        // Map status
        $this->status = match (strtolower($response['status'] ?? 'pending')) {
            'successful', 'completed' => Payment::STATUS_COMPLETED,
            'failed', 'cancelled' => Payment::STATUS_FAILED,
            'pending' => Payment::STATUS_PROCESSING,
            default => Payment::STATUS_FAILED,
        };

        $this->note = "Flutterwave Transaction: {$this->transactionId} (Status: {$this->status})";

        if (isset($response['processor_response'])) {
            $this->note .= " - {$response['processor_response']}";
        }

        $this->processedAt = isset($response['created_at'])
            ? new DateTime($response['created_at'])
            : new DateTime;

        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Extract standardized payment method metadata from Flutterwave response
     */
    protected function extractMetadata($data): array
    {
        // Ensure array format
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $normalized = [];

        // Payment method type
        $paymentType = $data['payment_type'] ?? null;
        $normalized['payment_method_type'] = $paymentType;

        // Card details
        if (isset($data['card'])) {
            $card = $data['card'];
            $normalized['card_brand'] = ucfirst($card['type'] ?? 'card');
            $normalized['last_four'] = $card['last_4digits'] ?? null;
            $normalized['issuer'] = $card['issuer'] ?? null;
            $normalized['country'] = $card['country'] ?? null;
        }

        // Mobile money details
        if ($paymentType === 'mobilemoney' || $paymentType === 'mpesa') {
            $normalized['wallet_type'] = 'mobile_money';
            $normalized['mobile_number'] = $data['customer']['phone_number'] ?? null;
        }

        // Bank transfer details
        if ($paymentType === 'banktransfer') {
            $normalized['bank_name'] = $data['meta']['originatorname'] ?? null;
            $normalized['account_number'] = $data['meta']['originatoraccountnumber'] ?? null;
        }

        // Customer information
        if (isset($data['customer'])) {
            $normalized['customer_email'] = $data['customer']['email'] ?? null;
            $normalized['customer_name'] = $data['customer']['name'] ?? null;
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
        $paymentType = $metadata['payment_method_type'] ?? null;

        // Card payment
        if ($paymentType === 'card' && isset($metadata['card_brand']) && isset($metadata['last_four'])) {
            $display = "{$metadata['card_brand']} •••• {$metadata['last_four']}";
            if (isset($metadata['issuer'])) {
                $display .= " ({$metadata['issuer']})";
            }

            return $display;
        }

        // Mobile money
        if (in_array($paymentType, ['mobilemoney', 'mpesa']) && isset($metadata['mobile_number'])) {
            return ucfirst($paymentType)." ({$metadata['mobile_number']})";
        }

        // Bank transfer
        if ($paymentType === 'banktransfer' && isset($metadata['bank_name'])) {
            return "Bank Transfer ({$metadata['bank_name']})";
        }

        // Unknown payment type
        if ($paymentType) {
            return ucfirst(str_replace('_', ' ', $paymentType));
        }

        return 'Flutterwave';
    }

    /**
     * Get additional Flutterwave-specific data
     */
    public function getFlutterwaveReference(): string
    {
        return $this->getTransactionId();
    }

    /**
     * Check if this is a successful Flutterwave payment
     */
    public function isSuccessful(): bool
    {
        return $this->getStatus() === Payment::STATUS_COMPLETED;
    }
}
