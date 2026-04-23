<?php

namespace Foundry\Payment\Mappers;

use DateTime;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;

class AlipayPayment extends AbstractPayment
{
    public function __construct($response, PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;

        // Yansongda response can be a Collection or object
        $data = is_array($response) ? $response : (method_exists($response, 'toArray') ? $response->toArray() : (array) $response);

        $this->transactionId = $data['trade_no'] ?? $data['out_trade_no'] ?? null;
        $this->amount = (float) ($data['total_amount'] ?? $data['buyer_pay_amount'] ?? 0);
        $this->currency = 'CNY';

        $tradeStatus = $data['trade_status'] ?? 'TRADE_SUCCESS';

        $this->status = match ($tradeStatus) {
            'TRADE_SUCCESS', 'TRADE_FINISHED' => Payment::STATUS_COMPLETED,
            'WAIT_BUYER_PAY' => Payment::STATUS_PROCESSING,
            'TRADE_CLOSED' => Payment::STATUS_CANCELLED,
            default => Payment::STATUS_FAILED,
        };

        $this->note = "Alipay Trade No: {$this->transactionId} (Status: {$tradeStatus})";

        $this->processedAt = isset($response->gmt_payment) ? new DateTime($response->gmt_payment) : new DateTime;
        $this->metadata = $this->extractMetadata($response);
    }

    protected function extractMetadata($response): array
    {
        // Ensure safe access for object properties, as $response might be an array or object
        $outTradeNo = is_object($response) ? ($response->out_trade_no ?? null) : ($response['out_trade_no'] ?? null);
        $buyerId = is_object($response) ? ($response->buyer_id ?? null) : ($response['buyer_id'] ?? null);
        $buyerLogonId = is_object($response) ? ($response->buyer_logon_id ?? null) : ($response['buyer_logon_id'] ?? null);

        // Fund channel information
        $fundChannels = is_object($response) ? ($response->fund_bill_list ?? []) : ($response['fund_bill_list'] ?? []);

        $normalized = [
            'payment_method_type' => 'alipay',
            'out_trade_no' => $outTradeNo,
            'buyer_id' => $buyerId,
            'buyer_logon_id' => $buyerLogonId,
            'fund_channels' => is_array($fundChannels) ? $fundChannels : (method_exists($fundChannels, 'toArray') ? $fundChannels->toArray() : (array) $fundChannels),
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
        if (! empty($metadata['fund_channels'])) {
            $channels = array_map(function ($bill) {
                $channel = is_object($bill) ? ($bill->fund_channel ?? 'UNKNOWN') : ($bill['fund_channel'] ?? 'UNKNOWN');

                return match (strtoupper($channel)) {
                    'ALIPAYACCOUNT' => 'Alipay Balance',
                    'PCREDIT' => 'Ant Credit Pay (Huabei)',
                    'MONEYFUND' => 'Yu\'e Bao',
                    'BANKCARD' => 'Bank Card',
                    'DEBIT_CARD_EXPRESS' => 'Debit Card (Express)',
                    'CREDIT_CARD_EXPRESS' => 'Credit Card (Express)',
                    'POINT' => 'Alipay Points',
                    'COUPON' => 'Coupon',
                    'MCARD' => 'Merchant Card',
                    'MDISCOUNT' => 'Merchant Discount',
                    default => ucfirst(strtolower(str_replace('_', ' ', $channel))),
                };
            }, $metadata['fund_channels']);

            return implode(', ', array_unique($channels));
        }

        return 'Alipay';
    }
}
