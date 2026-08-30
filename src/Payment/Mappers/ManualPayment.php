<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Services\PaymentProvider;

class ManualPayment extends AbstractPayment
{
    /**
     * Create from manual payment data
     *
     * @param  array  $response  Manual payment data including reference_number, payment_type, etc.
     * @param  mixed  $paymentMethod  Payment method
     */
    public function __construct(array $response)
    {
        // Set payment method
        $this->paymentMethod = PaymentProvider::MANUAL;

        $this->transactionId = $response['transaction_id'] ?? uniqid('manual_');

        // Store amount in BASE currency
        $this->amount = (float) ($response['amount'] ?? 0);
        $this->currency = strtoupper($response['currency'] ?? config('app.currency', 'USD'));

        $this->status = Payment::STATUS_COMPLETED;
        $this->note = $response['note'] ?? 'Manual Payment';
        $this->processedAt = new DateTime;
        $this->metadata = $this->extractMetadata($response);
    }

    /**
     * Create manual payment with reference number
     * Convenience factory method for backward compatibility
     */
    public static function withReference(
        string $referenceNumber,
        mixed $paymentMethod = null,
        ?string $note = null,
        array $additionalData = []
    ): self {
        $paymentData = array_merge($additionalData, [
            'reference_number' => $referenceNumber,
            'note' => $note,
        ]);

        return new self($paymentData, $paymentMethod);
    }

    /**
     * Extract standardized payment method metadata from manual payment data
     */
    protected function extractMetadata($data): array
    {
        // Ensure array format
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $normalized = [];

        // Payment method type
        $paymentType = $data['payment_type'] ?? 'manual';
        $normalized['payment_method_type'] = $paymentType;

        // Check details
        if (isset($data['check_number'])) {
            $normalized['check_number'] = $data['check_number'];
            $normalized['bank_name'] = $data['check_bank'] ?? null;
        }

        // Bank transfer details
        if (isset($data['bank_reference'])) {
            $normalized['bank_reference'] = $data['bank_reference'];
            $normalized['bank_name'] = $data['bank_name'] ?? null;
        }

        // Reference number
        if (isset($data['reference_number'])) {
            $normalized['reference_number'] = $data['reference_number'];
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
        $paymentType = $metadata['payment_method_type'] ?? 'manual';

        // Check payment
        if (isset($metadata['check_number'])) {
            $display = "Check #{$metadata['check_number']}";
            if (isset($metadata['bank_name'])) {
                $display .= " ({$metadata['bank_name']})";
            }

            return $display;
        }

        // Bank transfer
        if (isset($metadata['bank_reference'])) {
            $display = 'Bank Transfer';
            if (isset($metadata['bank_name'])) {
                $display .= " ({$metadata['bank_name']})";
            }
            $display .= " - Ref: {$metadata['bank_reference']}";

            return $display;
        }

        // Cash payment
        if ($paymentType === 'cash') {
            return 'Cash Payment';
        }

        // Payment with reference number
        if (isset($metadata['reference_number'])) {
            return ucwords(str_replace('_', ' ', $paymentType))." - Ref: {$metadata['reference_number']}";
        }

        return ucwords(str_replace('_', ' ', $paymentType));
    }
}
