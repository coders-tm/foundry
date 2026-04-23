<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;

class MercadoPagoPayment extends AbstractPayment
{
    /**
     * Create from MercadoPago payment or preference response
     *
     * @param  object|array  $response  MercadoPago payment or preference response
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

        $this->transactionId = $response['id'] ?? uniqid('mp_');

        // Store amount and currency from response
        $this->amount = $response['transaction_amount'] ?? 0;
        $this->currency = strtoupper($response['currency_id'] ?? config('app.currency', 'USD'));

        // Determine if this is a payment or preference
        if (isset($response['status'])) {
            // Payment response
            $this->status = match ($response['status']) {
                'approved' => Payment::STATUS_COMPLETED,
                'pending', 'in_process' => Payment::STATUS_PROCESSING,
                'rejected', 'cancelled' => Payment::STATUS_FAILED,
                'refunded', 'charged_back' => Payment::STATUS_CANCELLED,
                default => Payment::STATUS_FAILED,
            };

            $this->note = "MercadoPago Payment: {$this->transactionId} (Status: {$response['status']})";
            if (isset($response['status_detail'])) {
                $this->note .= " - {$response['status_detail']}";
            }
        } else {
            // Preference response
            $this->status = Payment::STATUS_PROCESSING;
            $this->note = "MercadoPago Preference: {$this->transactionId}";
        }

        $this->processedAt = new DateTime;
        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Extract standardized payment method metadata from MercadoPago response
     */
    protected function extractMetadata($data): array
    {
        // Ensure array format
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $normalized = [];

        // Extract payment type and method
        $paymentType = $data['payment_type_id'] ?? null;
        $normalized['payment_method_type'] = $paymentType;
        $normalized['payment_method_id'] = $data['payment_method_id'] ?? null;

        // Card details
        if (isset($data['card'])) {
            $card = $data['card'];
            $normalized['card_brand'] = ucfirst($card['id'] ?? 'card');
            $normalized['last_four'] = $card['last_four_digits'] ?? null;
            $normalized['cardholder_name'] = $card['cardholder']['name'] ?? null;
        }

        // Installments
        if (isset($data['installments']) && $data['installments'] > 1) {
            $normalized['installments'] = $data['installments'];
        }

        // Ticket/Boleto method
        if (in_array($paymentType, ['ticket', 'atm'])) {
            $normalized['ticket_method'] = $data['payment_method_id'] ?? null;
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

        // Credit/Debit Card
        if (in_array($paymentType, ['credit_card', 'debit_card']) && isset($metadata['card_brand']) && isset($metadata['last_four'])) {
            $display = ucfirst($metadata['card_brand'])." •••• {$metadata['last_four']}";

            if (isset($metadata['installments']) && $metadata['installments'] > 1) {
                $display .= " ({$metadata['installments']}x installments)";
            }

            return $display;
        }

        // Ticket/Boleto (Cash payment voucher)
        if ($paymentType === 'ticket') {
            return $this->getTicketMethodName($metadata['ticket_method'] ?? null);
        }

        // ATM
        if ($paymentType === 'atm') {
            return 'ATM';
        }

        // Bank transfer
        if ($paymentType === 'bank_transfer') {
            return 'Bank Transfer';
        }

        // Digital wallet
        if ($paymentType === 'digital_wallet' && isset($metadata['payment_method_id'])) {
            return ucfirst($metadata['payment_method_id']);
        }

        // Unknown payment type
        if ($paymentType) {
            return ucfirst(str_replace('_', ' ', $paymentType));
        }

        return 'Mercado Pago';
    }

    /**
     * Get ticket method display name
     */
    private function getTicketMethodName(?string $methodId): string
    {
        $methods = [
            'oxxo' => 'OXXO',
            'paycash' => 'PayCash',
            'bolbradesco' => 'Boleto Bradesco',
            'pec' => 'PEC',
            'redlink' => 'RedLink',
            'pagofacil' => 'Pago Fácil',
            'rapipago' => 'Rapipago',
        ];

        return $methods[$methodId] ?? 'Cash Voucher';
    }
}
