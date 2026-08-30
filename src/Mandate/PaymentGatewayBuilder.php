<?php

namespace Foundry\Mandate;

use Foundry\Mandate\Models\PaymentMethod;
use Foundry\Mandate\Responses\RedirectResponse;
use Foundry\Mandate\Services\GoCardlessPaymentService;
use Foundry\Mandate\Services\PaymentService;
use Foundry\Mandate\Services\PaypalPaymentService;
use Foundry\Mandate\Services\StripePaymentService;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Fluent builder for provider-specific PaymentService instances.
 */
class PaymentGatewayBuilder
{
    /**
     * The user model instance.
     *
     * @var Model|object|null
     */
    protected mixed $user = null;

    /**
     * The payment method reference.
     *
     * @var PaymentMethod|string|null
     */
    protected mixed $paymentMethod = null;

    /**
     * Create a new PaymentGatewayBuilder instance.
     *
     * @param  string  $provider  Payment provider name
     */
    private function __construct(protected readonly string $provider) {}

    /**
     * Instantiate a builder for the given provider.
     *
     * @param  string  $provider  Payment provider name (e.g. 'stripe', 'paypal', 'gocardless')
     *
     * @throws \InvalidArgumentException
     */
    public static function make(string $provider): self
    {
        if (! static::isSupported($provider)) {
            throw new \InvalidArgumentException("Unsupported payment provider: {$provider}");
        }

        return new self($provider);
    }

    /**
     * Set the user model.
     *
     * @param  Model|object  $user  User model or billable entity instance
     */
    public function setUser(mixed $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the payment method reference.
     *
     * @param  PaymentMethod|string|null  $paymentMethod  Payment method model or ID
     */
    public function setPaymentMethod(mixed $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    /**
     * Build the concrete provider PaymentService instance.
     *
     *
     * @throws \InvalidArgumentException
     */
    public function build(): PaymentService
    {
        return match ($this->provider) {
            PaymentProvider::STRIPE => new StripePaymentService($this->user, $this->paymentMethod),
            PaymentProvider::PAYPAL => new PaypalPaymentService($this->user, $this->paymentMethod),
            PaymentProvider::GOCARDLESS => new GoCardlessPaymentService($this->user, $this->paymentMethod),
            default => throw new \InvalidArgumentException("Unsupported payment provider: {$this->provider}"),
        };
    }

    /**
     * Set up a saved payment method.
     *
     * @return PaymentMethod|RedirectResponse|null
     */
    public function setup(): mixed
    {
        return $this->build()->setup();
    }

    /**
     * Remove a saved payment method.
     *
     * @return bool
     */
    public function remove(): mixed
    {
        return $this->build()->remove();
    }

    /**
     * Confirm a payment method setup.
     *
     * @param  array<string, mixed>  $options  Confirmation options
     */
    public function confirm(array $options): PaymentMethod
    {
        return $this->build()->confirm($options);
    }

    /**
     * Handle provider redirect callback.
     *
     * @param  Request  $request  HTTP request instance
     * @return PaymentMethod|RedirectResponse|bool
     */
    public function handleCallback(Request $request): mixed
    {
        return $this->build()->handleCallback($request);
    }

    /**
     * Charge a Payable entity.
     *
     * @param  Payable  $payable  Payable model or order instance to charge
     * @param  PaymentMethod|string|null  $paymentMethod  Optional payment method ID or model
     * @param  array<string, mixed>  $options  Additional options
     */
    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        return $this->build()->charge($payable, $paymentMethod, $options);
    }

    /**
     * Get list of supported provider names.
     *
     * @return array<int, string>
     */
    public static function getSupportedProviders(): array
    {
        return [
            PaymentProvider::STRIPE,
            PaymentProvider::PAYPAL,
            PaymentProvider::GOCARDLESS,
        ];
    }

    /**
     * Check if a provider name is supported.
     *
     * @param  string  $provider  Payment provider name
     */
    public static function isSupported(string $provider): bool
    {
        return in_array($provider, static::getSupportedProviders(), true);
    }
}
