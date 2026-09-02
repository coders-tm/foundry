<?php

use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('resolves correct public key and environment per provider config', function () {
    $stripeConfig = ['provider' => PaymentProvider::STRIPE, 'key' => 'pk_stripe_123', 'test_mode' => true];
    expect(PaymentProvider::getPublicKey($stripeConfig))->toBe([
        'public_key' => 'pk_stripe_123',
        'environment' => 'sandbox',
    ]);

    $razorpayConfig = ['provider' => PaymentProvider::RAZORPAY, 'key_id' => 'rzp_live_123'];
    expect(PaymentProvider::getPublicKey($razorpayConfig))->toBe([
        'public_key' => 'rzp_live_123',
        'environment' => null,
    ]);

    $paddleConfig = ['provider' => PaymentProvider::PADDLE, 'client_token' => 'live_ptok_123', 'environment' => 'sandbox'];
    expect(PaymentProvider::getPublicKey($paddleConfig))->toBe([
        'public_key' => 'live_ptok_123',
        'environment' => 'sandbox',
    ]);

    $paypalConfig = ['provider' => PaymentProvider::PAYPAL, 'client_id' => 'paypal_client_123', 'mode' => 'sandbox'];
    expect(PaymentProvider::getPublicKey($paypalConfig))->toBe([
        'public_key' => 'paypal_client_123',
        'environment' => 'sandbox',
    ]);

    $alipayConfig = ['provider' => PaymentProvider::ALIPAY, 'app_id' => 'alipay_app_123'];
    expect(PaymentProvider::getPublicKey($alipayConfig))->toBe([
        'public_key' => 'alipay_app_123',
        'environment' => null,
    ]);

    $genericConfig = ['provider' => 'custom', 'public_key' => 'custom_pk_123'];
    expect(PaymentProvider::getPublicKey($genericConfig))->toBe([
        'public_key' => 'custom_pk_123',
        'environment' => null,
    ]);
});
