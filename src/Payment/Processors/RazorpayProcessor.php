<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Mappers\RazorpayPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Payment\RefundResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Razorpay\Api\Errors\Error;

class RazorpayProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = ['INR', 'USD', 'EUR', 'GBP', 'SGD', 'AUD', 'CAD', 'MYR', 'HKD', 'AED', 'CNY', 'JPY', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'RUB', 'ZAR'];

    public function getProvider(): string
    {
        return PaymentMethod::RAZORPAY;
    }

    public function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    public function setupPaymentIntent(Request $request, Payable $payable): array
    {
        $api = Foundry::razorpay();

        // Ensure the payable supports the required currencies
        $payable->setCurrencies($this->supportedCurrencies());

        // Validate currency
        $this->validateCurrency($payable);

        $order = $api->order->create([
            'amount' => round($payable->getGatewayAmount() * 100), // Convert to paise
            'currency' => Str::upper($payable->getCurrency()),
            'receipt' => $payable->getReferenceId(),
            'notes' => array_merge(
                $payable->getMetadata(),
                [
                    'customer_email' => $payable->getCustomerEmail(),
                    'order_amount' => $payable->getGrandTotal(),
                    'order_currency' => ExchangeRate::getBaseCurrency(),
                ]
            ),
        ]);

        return [
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
        ];
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        $request->validate([
            'payment_id' => 'required|string',
            'order_id' => 'required|string',
            'signature' => 'required|string',
        ]);

        try {
            $api = Foundry::razorpay();

            // Verify payment signature
            $attributes = [
                'razorpay_order_id' => $request->order_id,
                'razorpay_payment_id' => $request->payment_id,
                'razorpay_signature' => $request->signature,
            ];

            $api->utility->verifyPaymentSignature($attributes);

            $payment_details = $api->payment->fetch($request->payment_id);

            if ($payment_details['status'] !== 'captured') {
                return PaymentResult::failed('Payment not captured');
            }

            $paymentData = new RazorpayPayment($payment_details, $this->paymentMethod);

            return PaymentResult::success(
                paymentData: $paymentData,
                transactionId: $request->payment_id,
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
     * Process a refund through Razorpay.
     *
     * @param  Payment  $payment  The payment to refund
     * @param  float|null  $amount  Amount to refund in base currency (null = full refund)
     * @param  string|null  $reason  Reason for the refund
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): RefundResult
    {
        try {
            $api = Foundry::razorpay();

            $refundParams = [];

            // Convert amount to paise
            if ($amount !== null) {
                $refundParams['amount'] = round($amount * 100);
            }

            // Add notes for reason if provided
            if ($reason) {
                $refundParams['notes'] = [
                    'reason' => $reason,
                ];
            }

            // Razorpay uses payment_id for refunds
            $refund = $api->payment->fetch($payment->transaction_id)->refund($refundParams);

            if (! $refund || ! isset($refund['id'])) {
                RefundResult::failed('Razorpay refund failed: No refund ID returned');
            }

            // Convert amount back from paise
            $refundedAmount = ($refund['amount'] ?? ($amount * 100)) / 100;

            return RefundResult::success(
                refundId: $refund['id'],
                amount: $refundedAmount,
                status: $refund['status'] ?? 'processed',
                metadata: [
                    'razorpay_refund_id' => $refund['id'],
                    'payment_id' => $payment->transaction_id,
                    'status' => $refund['status'] ?? 'processed',
                ]
            );
        } catch (Error $e) {
            RefundResult::failed('Razorpay refund error: '.$e->getMessage());
        } catch (\Throwable $e) {
            RefundResult::failed($e->getMessage());
        }
    }
}
