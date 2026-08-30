<?php

namespace Foundry\Payment\Processors;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Models\Payment;
use Foundry\Payment\CallbackResult;
use Foundry\Payment\Payable;
use Foundry\Payment\RefundResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

abstract class AbstractPaymentProcessor implements PaymentProcessorInterface
{
    /**
     * Get the payment provider name (must be implemented by child classes)
     */
    abstract public function getProvider(): string;

    /**
     * Get the list of supported currencies.
     * Return empty array to support all currencies.
     */
    public function supportedCurrencies(): array
    {
        return [];
    }

    /**
     * Default implementation for success callback
     * Returns success result - controller handles redirect
     * Override this in child classes for provider-specific behavior
     */
    public function handleSuccessCallback(Request $request): CallbackResult
    {
        return CallbackResult::success(
            message: 'Payment completed successfully!'
        );
    }

    /**
     * Default implementation for cancel callback
     * Returns success result - controller handles redirect
     * Override this in child classes for provider-specific behavior
     */
    public function handleCancelCallback(Request $request): CallbackResult
    {
        return CallbackResult::success(
            message: 'Payment was cancelled. You can try again or choose a different payment method.'
        );
    }

    /**
     * Get the success URL for this payment processor
     */
    protected function getSuccessUrl(array $params = []): string
    {
        $url = app_url("/payment/{$this->getProvider()}/success");

        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $url;
    }

    /**
     * Get the cancel URL for this payment processor
     */
    protected function getCancelUrl(array $params = []): string
    {
        $url = app_url("/payment/{$this->getProvider()}/cancel");

        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $url;
    }

    /**
     * Get the webhook URL for this payment processor
     */
    protected function getWebhookUrl(): string
    {
        return app_url("/api/{$this->getProvider()}/webhook");
    }

    /**
     * Get the payment provider slug/name for this processor
     */
    public function getPaymentMethod(): mixed
    {
        return $this->getProvider();
    }

    /**
     * Get the payment provider configuration array from registry
     */
    public function getConfig(): ?array
    {
        return PaymentProvider::find($this->getProvider());
    }

    /**
     * Process a refund for a payment.
     *
     * Default implementation throws "not supported" exception.
     * Override in child classes for gateway-specific refund logic.
     *
     * @param  Payment  $payment  The payment to refund
     * @param  float|null  $amount  Amount to refund (null = full refund)
     * @param  string|null  $reason  Reason for the refund
     */
    public function refund(Payment $payment, ?float $amount = null, ?string $reason = null): RefundResult
    {
        RefundResult::notSupported(
            "Refund is not supported for the {$this->getProvider()} payment provider"
        );
    }

    /**
     * Check if this payment processor supports refunds.
     *
     * Override in child classes that implement refund functionality.
     */
    public function supportsRefund(): bool
    {
        return false;
    }

    /**
     * Validate that the payable currency is supported by this processor.
     *
     * @throws ValidationException
     */
    public function validateCurrency(Payable $payable): void
    {
        $currency = $payable->getCurrency();
        $supportedCurrencies = $this->supportedCurrencies();

        // If supported currencies list is empty, it means all currencies are supported
        if (empty($supportedCurrencies)) {
            return;
        }

        if (! in_array(strtoupper($currency), $supportedCurrencies)) {
            throw ValidationException::withMessages([
                'currency' => "The currency {$currency} is not supported by {$this->getProvider()}.",
            ]);
        }
    }
}
