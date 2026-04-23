<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Foundry;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\CallbackResult;
use Foundry\Payment\Mappers\AlipayPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Payment\RefundResult;
use Illuminate\Http\Request;

class AlipayProcessor extends AbstractPaymentProcessor implements PaymentProcessorInterface
{
    private const SUPPORTED_CURRENCIES = ['CNY'];

    public function getProvider(): string
    {
        return PaymentMethod::ALIPAY;
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

        $alipay = Foundry::alipay();

        // Create a pending payment record first to get the uuid
        $payment = Payment::create([
            'paymentable_type' => $payable->isOrder() ? Foundry::$orderModel : get_class($payable->getSource()),
            'paymentable_id' => $payable->getSourceId(),
            'payment_method_id' => $this->getPaymentMethodId(),
            'transaction_id' => 'pending_'.uniqid(),
            'amount' => $payable->getGrandTotal(),
            'status' => Payment::STATUS_PENDING,
            'note' => 'Alipay payment initiated',
            'metadata' => array_merge($payable->getMetadata(), [
                'gateway_amount' => $payable->getGatewayAmount(),
                'gateway_currency' => $payable->getCurrency(),
                'created_at' => now()->toISOString(),
            ]),
        ]);

        $order = [
            'out_trade_no' => $payable->getReferenceId(),
            'total_amount' => number_format($payable->getGatewayAmount(), 2, '.', ''),
            'subject' => $payable->getDescription(),
            '_return_url' => $this->getSuccessUrl(['state' => $payment->uuid]),
        ];

        // Determine mode: web, wap, or app
        $mode = $request->input('mode', 'web');

        $result = match ($mode) {
            'wap' => $alipay->wap($order),
            'app' => $alipay->app($order),
            default => $alipay->web($order),
        };

        // For web/wap, it's usually a redirect
        if (method_exists($result, 'getTargetUrl')) {
            return [
                'redirect_url' => $result->getTargetUrl(),
                'payment_intent_id' => $payable->getReferenceId(),
                'amount' => $payable->getGatewayAmount(),
                'currency' => $payable->getCurrency(),
                'state_id' => $payment->uuid,
            ];
        }

        return [
            'payload' => $result->getContent(),
            'payment_intent_id' => $payable->getReferenceId(),
            'amount' => $payable->getGatewayAmount(),
            'currency' => $payable->getCurrency(),
            'state_id' => $payment->uuid,
        ];
    }

    public function confirmPayment(Request $request, Payable $payable): PaymentResult
    {
        try {
            $alipay = Foundry::alipay();
            $response = $alipay->verify();

            $paymentData = new AlipayPayment($response, $this->paymentMethod);
            $transactionId = is_object($response) ? ($response->trade_no ?? $response->out_trade_no) : ($response['trade_no'] ?? $response['out_trade_no'] ?? null);

            return PaymentResult::success(
                paymentData: $paymentData,
                transactionId: $transactionId,
                status: 'success'
            );
        } catch (\Throwable $e) {
            return PaymentResult::failed($e->getMessage());
        }
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): RefundResult
    {
        try {
            $alipay = Foundry::alipay();

            $order = [
                'out_trade_no' => $payment->transaction_id,
                'refund_amount' => number_format($amount ?? $payment->amount, 2, '.', ''),
                'refund_reason' => $reason ?? 'Customer request',
            ];

            $result = $alipay->refund($order);

            if ($result->code != '10000') {
                return RefundResult::failed(
                    "Alipay refund failed: {$result->sub_msg}"
                );
            }

            return RefundResult::success(
                refundId: $result->trade_no,
                amount: (float) $result->refund_fee,
                status: 'succeeded',
                metadata: [
                    'alipay_trade_no' => $result->trade_no,
                    'out_trade_no' => $result->out_trade_no,
                ]
            );
        } catch (\Throwable $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    public function handleSuccessCallback(Request $request): CallbackResult
    {
        try {
            $stateId = $request->query('state');

            if (! $stateId) {
                return CallbackResult::failed('Invalid payment session.');
            }

            $payment = Payment::where('uuid', $stateId)->first();

            if (! $payment) {
                return CallbackResult::failed('Payment not found.');
            }

            $alipay = Foundry::alipay();
            $response = $alipay->verify();

            $paymentData = new AlipayPayment($response, $this->paymentMethod);
            $payment->update($paymentData->toArray());

            return CallbackResult::success(
                message: 'Alipay payment was successful.',
                payment: $payment->fresh()
            );
        } catch (\Throwable $e) {
            return CallbackResult::failed('Payment verification failed: '.$e->getMessage());
        }
    }

    public function handleCancelCallback(Request $request): CallbackResult
    {
        $payment = null;
        try {
            $stateId = $request->query('state');

            if ($stateId) {
                $payment = Payment::where('uuid', $stateId)->first();
                if ($payment) {
                    $payment->markAsFailed('Payment cancelled by user');
                }
            }
        } catch (\Throwable $e) {
            // Log error
        }

        return CallbackResult::success(
            message: 'Alipay payment was cancelled.',
            payment: $payment
        );
    }
}
