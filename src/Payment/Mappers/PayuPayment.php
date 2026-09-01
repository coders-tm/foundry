<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Services\PaymentProvider;

class PayuPayment extends AbstractPayment
{
    public string $type = 'card';

    public ?string $cardBrand = 'PayU';

    public ?string $cardLastFour = null;

    public ?string $vpa = null;

    public function __construct(array $response)
    {
        $this->paymentMethod = PaymentProvider::PAYU;

        $this->transactionId = $response['mihpayid'] ?? $response['txnid'] ?? '';
        $this->amount = (float) ($response['amount'] ?? 0);
        $this->currency = $response['currency'] ?? 'INR';

        $rawStatus = $response['status'] ?? 'failure';

        $this->status = match ($rawStatus) {
            'success' => Payment::STATUS_COMPLETED,
            'pending' => Payment::STATUS_PROCESSING,
            default => Payment::STATUS_FAILED,
        };

        $this->note = "PayU Mihpayid: {$this->transactionId} (Status: {$rawStatus})";

        $addedOn = $response['addedon'] ?? null;
        $this->processedAt = $addedOn ? new DateTime($addedOn) : new DateTime;

        // Detect payment details
        $mode = strtolower($response['mode'] ?? '');
        $pgType = strtolower($response['PG_TYPE'] ?? '');
        $bankcode = strtolower($response['bankcode'] ?? '');
        $this->vpa = $response['field1'] ?? null;

        if (str_contains($mode, 'upi') || str_contains($pgType, 'upi') || str_contains($bankcode, 'upi')) {
            $this->type = 'upi';
            $this->vpa = $response['field1'] ?? $response['field3'] ?? $response['field4'] ?? null;
        } elseif (str_contains($mode, 'nb') || str_contains($pgType, 'nb') || str_contains($mode, 'netbanking') || str_contains($pgType, 'netbanking')) {
            $this->type = 'netbanking';
        }

        if ($this->type === 'upi') {
            $this->cardBrand = 'UPI';
            $this->cardLastFour = $this->vpa ?? 'UPI';
        } elseif ($this->type === 'netbanking') {
            $this->cardBrand = $response['bankcode'] ?? $response['PG_TYPE'] ?? 'NetBanking';
            $this->cardLastFour = 'Bank';
        } else {
            $this->cardBrand = $response['card_brand'] ?? $response['mode'] ?? 'PayU';
            $cardNo = $response['card_no'] ?? $response['cardnum'] ?? null;
            if ($cardNo) {
                $cleanedCard = preg_replace('/[^0-9]/', '', $cardNo);
                $this->cardLastFour = substr($cleanedCard, -4);
            }
            if (empty($this->cardLastFour)) {
                $this->cardLastFour = '1234';
            }
        }

        $this->metadata = $this->extractMetadata($response);
    }

    protected function extractMetadata($response): array
    {
        $normalized = [
            'payment_method_type' => 'payu',
            'mihpayid' => $response['mihpayid'] ?? null,
            'txnid' => $response['txnid'] ?? null,
            'mode' => $response['mode'] ?? null,
            'bank_ref_num' => $response['bank_ref_num'] ?? null,
            'bankcode' => $response['bankcode'] ?? null,
            'cardnum' => $this->cardLastFour,
            'vpa' => $this->vpa,
            'type' => $this->type,
            'card_brand' => $this->cardBrand,
            'name_on_card' => $response['name_on_card'] ?? null,
            'error_message' => $response['error_Message'] ?? $response['field9'] ?? null,
        ];

        // Build human-readable display string
        $normalized['payment_method'] = $this->buildDisplayString($normalized);

        return array_filter($normalized);
    }

    /**
     * Build human-readable payment method display string
     */
    private function buildDisplayString(array $metadata): string
    {
        $mode = strtoupper($metadata['mode'] ?? '');
        $bank = $metadata['bankcode'] ?? '';

        $display = match ($mode) {
            'CC' => 'Credit Card',
            'DC' => 'Debit Card',
            'NB' => 'Net Banking'.($bank ? " ($bank)" : ''),
            'UPI' => 'UPI',
            'CASH' => 'Cash',
            'EMI' => 'EMI',
            default => 'PayU',
        };

        if ($metadata['type'] === 'upi' && ! empty($metadata['vpa'])) {
            $display .= " ({$metadata['vpa']})";
        } elseif (! empty($metadata['cardnum']) && $metadata['type'] === 'card') {
            $display .= " (•••• {$metadata['cardnum']})";
        }

        return $display;
    }
}
