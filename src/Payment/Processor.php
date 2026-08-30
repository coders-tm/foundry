<?php

namespace Foundry\Payment;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Payment\Processors\AlipayProcessor;
use Foundry\Payment\Processors\FlutterwaveProcessor;
use Foundry\Payment\Processors\KlarnaProcessor;
use Foundry\Payment\Processors\ManualProcessor;
use Foundry\Payment\Processors\MercadoPagoProcessor;
use Foundry\Payment\Processors\PaddleProcessor;
use Foundry\Payment\Processors\PaypalProcessor;
use Foundry\Payment\Processors\PaystackProcessor;
use Foundry\Payment\Processors\RazorpayProcessor;
use Foundry\Payment\Processors\StripeProcessor;
use Foundry\Payment\Processors\WalletProcessor;
use Foundry\Payment\Processors\XenditProcessor;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;

class Processor
{
    /**
     * Create a payment processor instance for the given provider
     */
    public static function make(string $provider): PaymentProcessorInterface
    {
        return match ($provider) {
            PaymentProvider::STRIPE => new StripeProcessor,
            PaymentProvider::RAZORPAY => new RazorpayProcessor,
            PaymentProvider::PAYPAL => new PaypalProcessor,
            PaymentProvider::KLARNA => new KlarnaProcessor,
            PaymentProvider::MANUAL => new ManualProcessor,
            PaymentProvider::WALLET => new WalletProcessor,
            PaymentProvider::MERCADOPAGO => new MercadoPagoProcessor,
            PaymentProvider::XENDIT => new XenditProcessor,
            PaymentProvider::PAYSTACK => new PaystackProcessor,
            PaymentProvider::FLUTTERWAVE => new FlutterwaveProcessor,
            PaymentProvider::ALIPAY => new AlipayProcessor,
            PaymentProvider::PADDLE => new PaddleProcessor,
            default => throw new \InvalidArgumentException("Unsupported payment provider: {$provider}")
        };
    }

    /**
     * Get all supported payment providers
     */
    public static function getSupportedProviders(): array
    {
        return [
            PaymentProvider::STRIPE,
            PaymentProvider::RAZORPAY,
            PaymentProvider::PAYPAL,
            PaymentProvider::KLARNA,
            PaymentProvider::MANUAL,
            PaymentProvider::WALLET,
            PaymentProvider::MERCADOPAGO,
            PaymentProvider::XENDIT,
            PaymentProvider::PAYSTACK,
            PaymentProvider::FLUTTERWAVE,
            PaymentProvider::ALIPAY,
            PaymentProvider::PADDLE,
        ];
    }

    /**
     * Check if a provider is supported
     */
    public static function isSupported(string $provider): bool
    {
        return in_array($provider, self::getSupportedProviders());
    }

    /**
     * Handle success callback for a provider
     */
    public static function handleSuccessCallback(string $provider, Request $request): CallbackResult
    {
        if (! self::isSupported($provider)) {
            return CallbackResult::failed(
                message: 'Unsupported payment provider'
            );
        }

        try {
            $processor = self::make($provider);

            return $processor->handleSuccessCallback($request);
        } catch (\Throwable $e) {
            return CallbackResult::failed(
                message: 'Error processing payment callback: '.$e->getMessage()
            );
        }
    }

    /**
     * Handle cancel callback for a provider
     */
    public static function handleCancelCallback(string $provider, Request $request): CallbackResult
    {
        if (! self::isSupported($provider)) {
            return CallbackResult::failed(
                message: 'Unsupported payment provider'
            );
        }

        try {
            $processor = self::make($provider);

            return $processor->handleCancelCallback($request);
        } catch (\Throwable $e) {
            return CallbackResult::failed(
                message: 'Error processing payment cancellation: '.$e->getMessage()
            );
        }
    }
}
