<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;

class RazorpayPayment extends AbstractPayment
{
    /**
     * Create from Razorpay Payment or Order object
     *
     * @param  object  $response  Razorpay payment/order response object
     * @param  PaymentMethod  $paymentMethod  Payment method (required)
     */
    public function __construct($response, PaymentMethod $paymentMethod)
    {
        // Set payment method
        $this->paymentMethod = $paymentMethod;

        $this->transactionId = $response->id ?? uniqid('rzp_');

        // Use gateway amount and currency directly
        // The AbstractPayment base class will automatically store these in metadata
        // as gateway_amount and gateway_currency
        $this->amount = ($response->amount ?? 0) / 100;
        $this->currency = strtoupper($response->currency ?? config('app.currency', 'USD'));

        // Map Razorpay status
        $this->status = match ($response->status ?? 'created') {
            'captured', 'authorized' => Payment::STATUS_COMPLETED,
            'failed' => Payment::STATUS_FAILED,
            'refunded' => Payment::STATUS_REFUNDED,
            default => Payment::STATUS_PROCESSING,
        };

        $this->note = "Razorpay Payment: {$this->transactionId} (Status: {$this->status})";

        if (isset($response->error_description)) {
            $this->note .= " - {$response->error_description}";
        }

        $this->processedAt = isset($response->created_at)
            ? new DateTime("@{$response->created_at}")
            : new DateTime;

        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Extract standardized payment method metadata from Razorpay response
     *
     * @param  \Razorpay\Api\Payment  $response  Razorpay payment response object
     */
    protected function extractMetadata($response): array
    {
        $normalized = [];

        // Extract payment method
        $method = $response->method ?? null;
        $normalized['payment_method_type'] = $method;

        // Card details
        if (isset($response->card)) {
            $card = $response->card;
            $normalized['card_brand'] = ucfirst($card->network ?? 'card');
            $normalized['last_four'] = $card->last4 ?? null;
            $normalized['issuer'] = $card->issuer ?? null;
            $normalized['card_type'] = $card->type ?? null;
        }

        // UPI details
        if (isset($response->vpa)) {
            $normalized['upi_id'] = $response->vpa;
            $normalized['wallet_type'] = 'upi';
        }

        // Wallet details
        if ($method === 'wallet' && isset($response->wallet)) {
            $normalized['wallet_type'] = strtolower($response->wallet);
        }

        // Netbanking details
        if ($method === 'netbanking' && isset($response->bank)) {
            $normalized['bank_name'] = strtoupper($response->bank);
        }

        // EMI details
        if (isset($response->emi) && $response->emi) {
            $normalized['emi_duration'] = $data['emi_duration'] ?? null;
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
        $method = $metadata['payment_method_type'] ?? null;

        // Card payment
        if ($method === 'card' && isset($metadata['card_brand']) && isset($metadata['last_four'])) {
            $display = "{$metadata['card_brand']} •••• {$metadata['last_four']}";
            if (isset($metadata['issuer'])) {
                $display .= " ({$metadata['issuer']})";
            }
            if (! empty($metadata['emi_duration'])) {
                $display .= " - EMI ({$metadata['emi_duration']} months)";
            }

            return $display;
        }

        // UPI payment
        if ($method === 'upi' && isset($metadata['upi_id'])) {
            return "UPI ({$metadata['upi_id']})";
        }

        // Wallet payment
        if ($method === 'wallet' && isset($metadata['wallet_type'])) {
            return ucfirst($metadata['wallet_type']).' Wallet';
        }

        // Netbanking
        if ($method === 'netbanking' && isset($metadata['bank_name'])) {
            return "Net Banking ({$metadata['bank_name']})";
        }

        // Unknown payment type
        if ($method) {
            return ucfirst(str_replace('_', ' ', $method));
        }

        return 'Razorpay';
    }
}
