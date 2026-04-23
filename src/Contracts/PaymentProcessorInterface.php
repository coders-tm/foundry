<?php

namespace Foundry\Contracts;

use Foundry\Exceptions\PaymentException;
use Foundry\Exceptions\RefundException;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\CallbackResult;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Payment\RefundResult;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

interface PaymentProcessorInterface
{
    /**
     * Setup payment intent for the provider
     */
    public function setupPaymentIntent(Request $request, Payable $payable): array;

    /**
     * Confirm payment for the provider
     *
     * @throws PaymentException
     */
    public function confirmPayment(Request $request, Payable $payable): PaymentResult;

    /**
     * Handle successful payment callback
     */
    public function handleSuccessCallback(Request $request): CallbackResult;

    /**
     * Handle payment cancellation callback
     */
    public function handleCancelCallback(Request $request): CallbackResult;

    /**
     * Get the provider name
     */
    public function getProvider(): string;

    /**
     * Get the list of supported currencies.
     * Return empty array to support all currencies.
     */
    public function supportedCurrencies(): array;

    /**
     * Validate that the payable currency is supported by this processor.
     *
     * @throws ValidationException
     */
    public function validateCurrency(Payable $payable): void;

    /**
     * Set the payment method for this processor
     */
    public function setPaymentMethod(PaymentMethod $paymentMethod): PaymentProcessorInterface;

    /**
     * Get the payment method for this processor
     */
    public function getPaymentMethod(): ?PaymentMethod;

    /**
     * Process a refund for a payment.
     *
     * @param  Payment  $payment  The payment to refund
     * @param  float|null  $amount  Amount to refund (null = full refund)
     * @param  string|null  $reason  Reason for the refund
     *
     * @throws RefundException
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): RefundResult;

    /**
     * Check if this payment processor supports refunds.
     */
    public function supportsRefund(): bool;
}
