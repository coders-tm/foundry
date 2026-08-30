<?php

namespace Foundry\Mandate\Concerns;

use Foundry\Mandate\BillerManager;
use Foundry\Mandate\Models\PaymentMethod;
use Foundry\Mandate\Responses\RedirectResponse;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Http\Request;

/**
 * Manages off-session payment methods and charges for billable entities.
 */
trait Biller
{
    /**
     * Get the biller manager instance for the model.
     *
     * @param  string|null  $provider  Optional provider identifier (e.g. 'stripe', 'paypal', 'gocardless')
     */
    public function billable(?string $provider = null): BillerManager
    {
        $manager = new BillerManager($this);

        if ($provider !== null) {
            $manager->setProvider($provider);
        }

        return $manager;
    }

    /**
     * Get the status of the model's saved payment methods.
     *
     * @return array<string, mixed>
     */
    public function paymentMethodStatus(): array
    {
        return $this->billable()->paymentMethodStatus();
    }

    /**
     * Add or update a saved payment method.
     *
     * @param  PaymentMethod|string|null  $paymentMethod  The payment method ID or model instance
     * @param  string|null  $provider  Optional payment provider name
     * @return PaymentMethod|RedirectResponse
     */
    public function addPaymentMethod(mixed $paymentMethod = null, ?string $provider = null): mixed
    {
        return $this->billable($provider)
            ->setPaymentMethod($paymentMethod)
            ->setup();
    }

    /**
     * Remove a saved payment method.
     *
     * @param  string|null  $provider  Optional payment provider name
     * @return bool
     */
    public function removePaymentMethod(?string $provider = null): mixed
    {
        return $this->billable($provider)->removePaymentMethod();
    }

    /**
     * Confirm a payment method setup.
     *
     * @param  string  $provider  Payment provider name
     * @param  array<string, mixed>  $options  Confirmation options (e.g. payment_method, setup_intent)
     */
    public function confirmPaymentMethod(string $provider, array $options): PaymentMethod
    {
        return $this->billable($provider)->confirm($provider, $options);
    }

    /**
     * Find a saved payment method by provider and provider ID.
     *
     * @param  string  $provider  Payment provider name
     * @param  string  $providerId  External provider payment method ID
     */
    public function findPaymentMethod(string $provider, string $providerId): PaymentMethod
    {
        return $this->billable()->findPaymentMethod($provider, $providerId);
    }

    /**
     * Handle provider redirect callback.
     *
     * @param  string  $provider  Payment provider name
     * @param  Request  $request  HTTP request instance
     * @return PaymentMethod|RedirectResponse|bool
     */
    public function handlePaymentCallback(string $provider, Request $request): mixed
    {
        return $this->billable($provider)->handleCallback($request);
    }

    /**
     * Charge a Payable entity using a saved payment method.
     *
     * @param  Payable  $payable  The payable model or order instance to charge
     * @param  PaymentMethod|string|null  $paymentMethod  Optional specific payment method to use
     * @param  array<string, mixed>  $options  Additional charge options
     */
    public function charge(
        Payable $payable,
        mixed $paymentMethod = null,
        array $options = [],
    ): PaymentResult {
        return $this->billable()->charge($payable, $paymentMethod, $options);
    }
}
