<?php

namespace Foundry\Payment;

use Foundry\Contracts\PaymentProcessorInterface;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Processors\AlipayProcessor;
use Foundry\Payment\Processors\FlutterwaveProcessor;
use Foundry\Payment\Processors\KlarnaProcessor;
use Foundry\Payment\Processors\ManualProcessor;
use Foundry\Payment\Processors\MercadoPagoProcessor;
use Foundry\Payment\Processors\PaypalProcessor;
use Foundry\Payment\Processors\PaystackProcessor;
use Foundry\Payment\Processors\RazorpayProcessor;
use Foundry\Payment\Processors\StripeProcessor;
use Foundry\Payment\Processors\WalletProcessor;
use Foundry\Payment\Processors\XenditProcessor;
use Illuminate\Http\Request;

class Processor
{
    /**
     * Create a payment processor instance for the given provider
     */
    public static function make(string $provider): PaymentProcessorInterface
    {
        return match ($provider) {
            'stripe' => new StripeProcessor,
            'razorpay' => new RazorpayProcessor,
            'paypal' => new PaypalProcessor,
            'klarna' => new KlarnaProcessor,
            'manual' => new ManualProcessor,
            'wallet' => new WalletProcessor,
            'mercadopago' => new MercadoPagoProcessor,
            'xendit' => new XenditProcessor,
            'paystack' => new PaystackProcessor,
            'flutterwave' => new FlutterwaveProcessor,
            'alipay' => new AlipayProcessor,
            default => throw new \InvalidArgumentException("Unsupported payment provider: {$provider}")
        };
    }

    /**
     * Get all supported payment providers
     */
    public static function getSupportedProviders(): array
    {
        return [
            'stripe',
            'razorpay',
            'paypal',
            'klarna',
            'manual',
            'wallet',
            'mercadopago',
            'xendit',
            'paystack',
            'flutterwave',
            'alipay',
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
            $paymentMethod = PaymentMethod::byProvider($provider);
            $processor = self::make($provider);
            $processor->setPaymentMethod($paymentMethod);

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
            $paymentMethod = PaymentMethod::byProvider($provider);
            $processor = self::make($provider);
            $processor->setPaymentMethod($paymentMethod);

            return $processor->handleCancelCallback($request);
        } catch (\Throwable $e) {
            return CallbackResult::failed(
                message: 'Error processing payment cancellation: '.$e->getMessage()
            );
        }
    }
}
