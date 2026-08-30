<?php

namespace Foundry\Services;

use Foundry\Mandate\PaymentGatewayBuilder;
use Illuminate\Support\Collection;

class PaymentProvider
{
    public const STRIPE = 'stripe';

    public const RAZORPAY = 'razorpay';

    public const PAYPAL = 'paypal';

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
        $existing = config("foundry.payment_providers.{$provider}", []);

        $merged = array_merge([
            'provider' => $provider,
            'enabled' => true,
        ], $existing, $config);

        config(["foundry.payment_providers.{$provider}" => $merged]);
    }

    /**
     * Remove a payment provider configuration.
     */
    public static function remove(string $provider): void
    {
        config()->offsetUnset("foundry.payment_providers.{$provider}");
    }

    /**
     * Get all payment providers configuration from foundry config.
     */
    public static function all(): Collection
    {
        $providers = config('foundry.payment_providers', []);

        return collect($providers);
    }

    /**
     * Get enabled payment providers.
     */
    public static function enabled(): Collection
    {
        return static::all()->filter(function ($config) {
            return is_array($config) && ($config['enabled'] ?? false);
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
        $config = config("foundry.payment_providers.{$provider}");

        if (! is_array($config) || ! ($config['enabled'] ?? false)) {
            return null;
        }

        return $config;
    }

    /**
     * Resolve public key for a provider configuration array based on config keys.
     */
    public static function getPublicKey(array $item): ?string
    {
        $provider = $item['provider'] ?? null;

        return match ($provider) {
            self::STRIPE => $item['key'] ?? null,
            self::PAYPAL => $item['client_id'] ?? null,
            self::RAZORPAY => $item['key_id'] ?? null,
            self::PADDLE => $item['client_token'] ?? null,
            self::ALIPAY => $item['app_id'] ?? null,
            self::MERCADOPAGO,
            self::PAYSTACK,
            self::XENDIT,
            self::FLUTTERWAVE => $item['public_key'] ?? null,
            default => $item['public_key'] ?? $item['client_id'] ?? null,
        };
    }

    /**
     * Get providers for public checkout rendering.
     */
    public static function toPublic(): Collection
    {
        return static::enabled()
            ->sortBy(fn ($item) => is_array($item) ? ($item['order'] ?? 99) : 99)
            ->values()
            ->map(function ($item) {
                if (! is_array($item)) {
                    return [];
                }

                $publicKey = static::getPublicKey($item);

                return [
                    'id' => $item['provider'] ?? null,
                    'name' => $item['name'] ?? $item['provider'] ?? '',
                    'label' => $item['label'] ?? $item['name'] ?? $item['provider'] ?? '',
                    'provider' => $item['provider'] ?? null,
                    'logo' => $item['logo'] ?? null,
                    'payment_instructions' => $item['payment_instructions'] ?? null,
                    'additional_details' => $item['additional_details'] ?? null,
                    'methods' => $item['methods'] ?? [],
                    'transaction_fee' => $item['transaction_fee'] ?? null,
                    'public_key' => $publicKey,
                ];
            });
    }

    /**
     * Get mandate-capable payment providers for public rendering.
     */
    public static function toPublicMandateable(): Collection
    {
        return static::toPublic()
            ->filter(function ($item) {
                $provider = $item['provider'] ?? null;

                return $provider && PaymentGatewayBuilder::isSupported($provider);
            })
            ->values();
    }
}
