<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;

class XenditPayment extends AbstractPayment
{
    /**
     * Create from Xendit invoice or payment response
     *
     * @param  object|array  $response  Xendit invoice, payment, or payment request response
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

        // Prefer transaction_id over id when available
        $this->transactionId = $response['transaction_id'] ?? $response['id'] ?? uniqid('xendit_');

        // Use the actual gateway amount
        $this->amount = $response['amount'] ?? 0;
        $this->currency = $response['currency'] ?? config('app.currency', 'USD');

        // Map Xendit statuses
        $this->status = match ($response['status'] ?? 'UNKNOWN') {
            'PAID', 'SETTLED', 'SUCCEEDED', 'COMPLETED' => Payment::STATUS_COMPLETED,
            'PENDING', 'PROCESSING' => Payment::STATUS_PROCESSING,
            'FAILED', 'EXPIRED' => Payment::STATUS_FAILED,
            'CANCELLED' => Payment::STATUS_CANCELLED,
            default => Payment::STATUS_FAILED,
        };

        $this->note = "Xendit Payment: {$this->transactionId} (Status: {$this->status})";

        if (isset($response['failure_code'])) {
            $this->note .= " - Failure: {$response['failure_code']}";
        }

        if (isset($response['external_id'])) {
            $this->note .= " - External ID: {$response['external_id']}";
        }

        $this->processedAt = new DateTime;
        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Extract standardized payment method metadata from Xendit response
     */
    protected function extractMetadata($data): array
    {
        // Ensure array format
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $normalized = [];

        // Extract payment channel (from callback/webhook data)
        $channelCode = $data['channel_code'] ?? null;
        $normalized['channel_code'] = $channelCode;

        // Extract payment_method (from invoice response - CREDIT_CARD, EWALLET, etc.)
        $paymentMethod = $data['payment_method'] ?? null;
        if (is_object($paymentMethod)) {
            $paymentMethod = (string) $paymentMethod;
        }

        // Extract payment_channel (from callback/webhook - more specific like OVO, BNI, etc.)
        $paymentChannel = $data['payment_channel'] ?? null;

        // Credit/Debit Card details (webhook/callback data)
        if (isset($data['credit_card_charge_id']) || $paymentMethod === 'CREDIT_CARD') {
            $normalized['payment_method_type'] = 'card';
            $normalized['card_brand'] = strtoupper($data['card_brand'] ?? 'card');
            if ($maskedNumber = $data['masked_card_number'] ?? null) {
                $normalized['last_four'] = substr($maskedNumber, -4);
            }
        }
        // E-Wallet details (webhook/callback or invoice response)
        elseif (isset($data['ewallet_type']) || $paymentMethod === 'EWALLET' || in_array($channelCode, ['ID_OVO', 'ID_DANA', 'ID_LINKAJA', 'PH_GCASH', 'PH_PAYMAYA'])) {
            $normalized['payment_method_type'] = 'wallet';
            $normalized['wallet_type'] = $data['ewallet_type']
                ?? static::getEwalletFromChannel($channelCode)
                ?? static::getEwalletFromChannel($paymentChannel);
        }
        // Virtual Account details (webhook/callback or invoice response)
        elseif (isset($data['bank_code']) || $paymentMethod === 'BANK_TRANSFER' || $paymentMethod === 'CALLBACK_VIRTUAL_ACCOUNT') {
            $normalized['payment_method_type'] = 'virtual_account';
            $normalized['bank_name'] = static::getBankName($data['bank_code'] ?? $paymentChannel ?? 'UNKNOWN');
        }
        // Retail Outlet details (webhook/callback or invoice response)
        elseif (isset($data['retail_outlet_name']) || $paymentMethod === 'RETAIL_OUTLET') {
            $normalized['payment_method_type'] = 'retail_outlet';
            $normalized['retail_outlet'] = $data['retail_outlet_name'] ?? $paymentChannel ?? 'Retail Outlet';
        }
        // QR Code details (webhook/callback or invoice response)
        elseif (isset($data['qr_string']) || $paymentMethod === 'QR_CODE' || $paymentMethod === 'QRIS') {
            $normalized['payment_method_type'] = 'qr_code';
        }
        // Direct Debit details
        elseif (isset($data['direct_debit_type']) || $paymentMethod === 'DIRECT_DEBIT') {
            $normalized['payment_method_type'] = 'direct_debit';
        }
        // Paylater
        elseif ($paymentMethod === 'PAYLATER') {
            $normalized['payment_method_type'] = 'paylater';
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
        $type = $metadata['payment_method_type'] ?? null;

        // Credit/Debit Card
        if ($type === 'card' && isset($metadata['card_brand']) && isset($metadata['last_four'])) {
            return "{$metadata['card_brand']} •••• {$metadata['last_four']}";
        }

        // E-Wallet
        if ($type === 'wallet' && isset($metadata['wallet_type'])) {
            return static::formatEwalletName($metadata['wallet_type']);
        }

        // Virtual Account
        if ($type === 'virtual_account' && isset($metadata['bank_name'])) {
            return "Virtual Account ({$metadata['bank_name']})";
        }

        // Retail Outlet
        if ($type === 'retail_outlet' && isset($metadata['retail_outlet'])) {
            return $metadata['retail_outlet'];
        }

        // QR Code
        if ($type === 'qr_code') {
            return 'QR Code';
        }

        // Direct Debit
        if ($type === 'direct_debit') {
            return 'Direct Debit';
        }

        // Paylater
        if ($type === 'paylater') {
            return 'Paylater';
        }

        // Unknown payment type
        if ($type) {
            return ucfirst(str_replace('_', ' ', $type));
        }

        return 'Xendit';
    }

    /**
     * Get e-wallet type from channel code
     */
    private static function getEwalletFromChannel(?string $channelCode): ?string
    {
        $mapping = [
            'ID_OVO' => 'OVO',
            'ID_DANA' => 'DANA',
            'ID_LINKAJA' => 'LinkAja',
            'PH_GCASH' => 'GCash',
            'PH_PAYMAYA' => 'PayMaya',
        ];

        return $mapping[$channelCode] ?? null;
    }

    /**
     * Format e-wallet name for display
     */
    private static function formatEwalletName(string $ewallet): string
    {
        $names = [
            'ID_OVO' => 'OVO',
            'ID_DANA' => 'DANA',
            'ID_LINKAJA' => 'LinkAja',
            'PH_GCASH' => 'GCash',
            'PH_PAYMAYA' => 'PayMaya',
            'OVO' => 'OVO',
            'DANA' => 'DANA',
            'LINKAJA' => 'LinkAja',
        ];

        return $names[$ewallet] ?? ucfirst(strtolower($ewallet));
    }

    /**
     * Get bank name from bank code
     */
    private static function getBankName(string $bankCode): string
    {
        $banks = [
            'BCA' => 'BCA',
            'BNI' => 'BNI',
            'BRI' => 'BRI',
            'MANDIRI' => 'Mandiri',
            'PERMATA' => 'Permata',
            'BSI' => 'BSI',
            'CIMB' => 'CIMB Niaga',
        ];

        return $banks[$bankCode] ?? $bankCode;
    }
}
