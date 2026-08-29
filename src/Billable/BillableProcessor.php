<?php

namespace Foundry\Billable;

use Foundry\Billable\Models\PaymentMethod;
use Foundry\Billable\Services\BillablePayment;
use Foundry\Billable\Services\GoCardlessPayment;
use Foundry\Billable\Services\PaypalPayment;
use Foundry\Billable\Services\StripePayment;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Illuminate\Http\Request;

/**
 * Fluent builder for provider-specific payment service instances.
 *
 * Entry point: BillableProcessor::make('stripe')
 *                  ->setUser($user)
 *                  ->setPaymentMethod($pm)
 *                  ->setup();
 *
 * Use build() if you need the raw BillablePayment service directly.
 * Use the proxy methods (setup, charge, etc.) for the common case.
 */
class BillableProcessor
{
    /**
     * The user model or billable entity instance.
     *
     * @var mixed
     */
    protected mixed $user = null;

    protected mixed $paymentMethod = null;

    private function __construct(protected readonly string $provider) {}

    /**
     * Named constructor — validates provider and returns a configured builder.
     *
     * @throws \InvalidArgumentException
     */
    public static function make(string $provider): static
    {
        if (! static::isSupported($provider)) {
            throw new \InvalidArgumentException("Unsupported billable payment provider: {$provider}");
        }

        return new static($provider);
    }

    /**
     * Bind the user to this builder instance.
     *
     * @param  mixed  $user  User model or billable entity instance
     * @return $this
     */
    public function setUser(mixed $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Bind the payment method to this builder instance.
     *
     * @return $this
     */
    public function setPaymentMethod(mixed $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    /**
     * Resolve and return the concrete provider service instance.
     *
     * @throws \InvalidArgumentException
     */
    public function build(): BillablePayment
    {
        return match ($this->provider) {
            'stripe'     => new StripePayment($this->user, $this->paymentMethod),
            'paypal'     => new PaypalPayment($this->user, $this->paymentMethod),
            'gocardless' => new GoCardlessPayment($this->user, $this->paymentMethod),
            default      => throw new \InvalidArgumentException("Unsupported billable payment provider: {$this->provider}"),
        };
    }

    public function setup(): mixed
    {
        return $this->build()->setup();
    }

    public function remove(): mixed
    {
        return $this->build()->remove();
    }

    public function confirm(array $options): PaymentMethod
    {
        return $this->build()->confirm($options);
    }

    public function handleCallback(Request $request): mixed
    {
        return $this->build()->handleCallback($request);
    }

    public function charge(Payable $payable, mixed $paymentMethod = null, array $options = []): PaymentResult
    {
        return $this->build()->charge($payable, $paymentMethod, $options);
    }

    /**
     * Get all supported payment provider identifiers.
     *
     * @return array<int, string>
     */
    public static function getSupportedProviders(): array
    {
        return ['stripe', 'paypal', 'gocardless'];
    }

    /**
     * Determine whether a provider identifier is supported.
     */
    public static function isSupported(string $provider): bool
    {
        return in_array($provider, static::getSupportedProviders(), true);
    }
}
