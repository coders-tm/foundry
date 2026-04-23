<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Foundry;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Mappers\StripePayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Payment\RefundResult;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;

class StripeProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = [
        'USD',
        'AED',
        'AFN',
        'ALL',
        'AMD',
        'ANG',
        'AOA',
        'ARS',
        'AUD',
        'AWG',
        'AZN',
        'BAM',
        'BBD',
        'BDT',
        'BGN',
        'BIF',
        'BMD',
        'BND',
        'BOB',
        'BRL',
        'BSD',
        'BWP',
        'BYN',
        'BZD',
        'CAD',
        'CDF',
        'CHF',
        'CLP',
        'CNY',
        'COP',
        'CRC',
        'CVE',
        'CZK',
        'DJF',
        'DKK',
        'DOP',
        'DZD',
        'EGP',
        'ETB',
        'EUR',
        'FJD',
        'FKP',
        'GBP',
        'GEL',
        'GIP',
        'GMD',
        'GNF',
        'GTQ',
        'GYD',
        'HKD',
        'HNL',
        'HTG',
        'HUF',
        'IDR',
        'ILS',
        'INR',
        'ISK',
        'JMD',
        'JPY',
        'KES',
        'KGS',
        'KHR',
        'KMF',
        'KRW',
        'KYD',
        'KZT',
        'LAK',
        'LBP',
        'LKR',
        'LRD',
        'LSL',
        'MAD',
        'MDL',
        'MGA',
        'MKD',
        'MMK',
        'MNT',
        'MOP',
        'MRO',
        'MUR',
        'MVR',
        'MWK',
        'MXN',
        'MYR',
        'MZN',
        'NAD',
        'NGN',
        'NIO',
        'NOK',
        'NPR',
        'NZD',
        'PAB',
        'PEN',
        'PGK',
        'PHP',
        'PKR',
        'PLN',
        'PYG',
        'QAR',
        'RON',
        'RSD',
        'RUB',
        'RWF',
        'SAR',
        'SBD',
        'SCR',
        'SEK',
        'SGD',
        'SHP',
        'SLL',
        'SOS',
        'SRD',
        'STD',
        'SZL',
        'THB',
        'TJS',
        'TOP',
        'TRY',
        'TTD',
        'TWD',
        'TZS',
        'UAH',
        'UGX',
        'UYU',
        'UZS',
        'VND',
        'VUV',
        'WST',
        'XAF',
        'XCD',
        'XOF',
        'XPF',
        'YER',
        'ZAR',
        'ZMW',
    ];

    public function getProvider(): string
    {
        return PaymentMethod::STRIPE;
    }

    public function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function setupPaymentIntent(Request $request, Payable $payable): array
    {
        // Ensure the payable supports the required currencies
        $payable->setCurrencies($this->supportedCurrencies());

        // Validate currency
        $this->validateCurrency($payable);

        $stripe = Foundry::stripe();

        $intent = $stripe->paymentIntents->create([
            'amount' => round($payable->getGatewayAmount() * 100), // Convert to cents
            'currency' => $payable->getCurrency(),
            'metadata' => $payable->getMetadata(),
            'description' => $payable->getDescription(),
            'receipt_email' => $payable->getCustomerEmail(),
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        return [
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
        ];
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            $stripe = Foundry::stripe();
            // Expand payment_method and latest_charge to get full payment details
            $intent = $stripe->paymentIntents->retrieve(
                $request->payment_intent_id,
                ['expand' => ['payment_method', 'latest_charge']]
            );

            // Handle different payment intent statuses
            if (! in_array($intent->status, ['succeeded', 'requires_capture'])) {
                return PaymentResult::failed(
                    "Payment not completed. Status: {$intent->status}".
                    ($intent->status === 'requires_action' ? ' (requires additional action)' : '')
                );
            }

            // Use new constructor pattern with SDK object
            $paymentData = new StripePayment($intent, $this->paymentMethod);

            return PaymentResult::success(
                paymentData: $paymentData,
                transactionId: $intent->id,
                status: 'success'
            );
        } catch (\Throwable $e) {
            return PaymentResult::failed($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * Process a refund through Stripe.
     *
     * @param  Payment  $payment  The payment to refund
     * @param  float|null  $amount  Amount to refund in base currency (null = full refund)
     * @param  string|null  $reason  Reason for the refund
     */
    /**
     * Process a refund through Stripe.
     *
     * @param  Payment  $payment  The payment to refund
     * @param  float|null  $amount  Amount to refund (ignored, always full refund)
     * @param  string|null  $reason  Reason for the refund
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): RefundResult
    {
        try {
            $stripe = Foundry::stripe();

            $refundParams = [
                'payment_intent' => $payment->transaction_id,
            ];

            // Enforce full refund by NOT sending amount
            // Stripe automatically refunds the remaining amount on the intent

            // Add reason if provided (Stripe supports: duplicate, fraudulent, requested_by_customer)
            if ($reason) {
                // Map common reasons to Stripe's accepted values
                $stripeReason = match (true) {
                    str_contains(strtolower($reason), 'duplicate') => 'duplicate',
                    str_contains(strtolower($reason), 'fraud') => 'fraudulent',
                    default => 'requested_by_customer',
                };
                $refundParams['reason'] = $stripeReason;
                $refundParams['metadata'] = ['original_reason' => $reason];
            }

            $refund = $stripe->refunds->create($refundParams);

            if ($refund->status !== 'succeeded' && $refund->status !== 'pending') {
                RefundResult::failed(
                    "Stripe refund failed with status: {$refund->status}"
                );
            }

            return RefundResult::success(
                refundId: $refund->id,
                amount: $refund->amount / 100, // Convert back from cents
                status: $refund->status,
                metadata: [
                    'stripe_refund_id' => $refund->id,
                    'payment_intent' => $refund->payment_intent,
                    'charge' => $refund->charge,
                    'reason' => $refund->reason,
                ]
            );
        } catch (ApiErrorException $e) {
            RefundResult::failed('Stripe refund error: '.$e->getMessage());
        } catch (\Throwable $e) {
            RefundResult::failed('Stripe error: '.$e->getMessage());
        }
    }
}
