<?php

namespace Foundry\Services;

use Illuminate\Support\Collection;

class PaymentProvider
{
    public const STRIPE = 'stripe';

    public const RAZORPAY = 'razorpay';

    public const PAYPAL = 'paypal';

    public const PAYU = 'payu';

    public const GOCARDLESS = 'gocardless';

    public const KLARNA = 'klarna';

    public const MERCADOPAGO = 'mercadopago';

    public const PAYSTACK = 'paystack';

    public const XENDIT = 'xendit';

    public const FLUTTERWAVE = 'flutterwave';

    public const APPLE_PAY = 'apple_pay';

    public const GOOGLE_PAY = 'google_pay';

    public const ALIPAY = 'alipay';

    public const PADDLE = 'paddle';

    public const MANUAL = 'manual';

    public const WALLET = 'wallet';

    /**
     * Add or update a payment provider configuration dynamically.
     */
    public static function add(string $provider, array $config): void
    {
        $existing = config("foundry.payment-providers.{$provider}", []);

        $merged = array_merge([
            'provider' => $provider,
            'enabled' => true,
        ], $existing, $config);

        config(["foundry.payment-providers.{$provider}" => $merged]);
    }

    /**
     * Remove a payment provider configuration.
     */
    public static function remove(string $provider): void
    {
        config()->offsetUnset("foundry.payment-providers.{$provider}");
    }

    /**
     * Get all payment providers configuration from foundry config.
     *
     * @return Collection<string, PaymentMethod>
     */
    public static function all(): Collection
    {
        $providers = config('foundry.payment-providers', []);

        return collect($providers)->map(function ($config, $key) {
            if (! is_array($config)) {
                return null;
            }

            $provider = $config['provider'] ?? $key;

            return PaymentMethod::fromArray(array_merge([
                'id' => $provider,
                'provider' => $provider,
            ], $config));
        })->filter();
    }

    /**
     * Get enabled payment providers.
     *
     * @return Collection<string, PaymentMethod>
     */
    public static function enabled(): Collection
    {
        return static::all()->filter(function (PaymentMethod $method) {
            return $method->enabled;
        });
    }

    /**
     * Check if provider exists and is enabled.
     */
    public static function has(string $provider): bool
    {
        return static::enabled()->has($provider);
    }

    /**
     * Find an enabled provider configuration by its string identifier.
     */
    public static function find(string $provider): ?array
    {
        $config = config("foundry.payment-providers.{$provider}");

        if (! is_array($config) || ! ($config['enabled'] ?? false)) {
            return null;
        }

        return $config;
    }

    /**
     * Resolve public attributes (public_key, environment, etc.) for a provider configuration array.
     */
    public static function getPublicKey(array $item): array
    {
        $method = PaymentMethod::fromArray($item);

        return [
            'public_key' => $method->publicKey,
            'environment' => $method->environment,
        ];
    }

    /**
     * Get providers for public checkout rendering.
     *
     * @return Collection<int, PaymentMethod>
     */
    public static function toPublic(): Collection
    {
        return static::enabled()
            ->sortBy(fn (PaymentMethod $item) => $item->order ?? 99)
            ->values();
    }
}
