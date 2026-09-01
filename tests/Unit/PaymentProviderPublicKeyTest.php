<?php

uses(Foundry\Tests\TestCase::class);

it('resolves correct public key and environment per provider config', function () {
    $stripeConfig = ['provider' => \Foundry\Services\PaymentProvider::STRIPE, 'key' => 'pk_stripe_123', 'test_mode' => true];
    $this->assertEquals([
        'public_key' => 'pk_stripe_123',
        'environment' => 'sandbox',
    ], \Foundry\Services\PaymentProvider::getPublicKey($stripeConfig));

    $razorpayConfig = ['provider' => \Foundry\Services\PaymentProvider::RAZORPAY, 'key_id' => 'rzp_live_123'];
    $this->assertEquals([
        'public_key' => 'rzp_live_123',
        'environment' => null,
    ], \Foundry\Services\PaymentProvider::getPublicKey($razorpayConfig));

    $paddleConfig = ['provider' => \Foundry\Services\PaymentProvider::PADDLE, 'client_token' => 'live_ptok_123', 'environment' => 'sandbox'];
    $this->assertEquals([
        'public_key' => 'live_ptok_123',
        'environment' => 'sandbox',
    ], \Foundry\Services\PaymentProvider::getPublicKey($paddleConfig));

    $paypalConfig = ['provider' => \Foundry\Services\PaymentProvider::PAYPAL, 'client_id' => 'paypal_client_123', 'mode' => 'sandbox'];
    $this->assertEquals([
        'public_key' => 'paypal_client_123',
        'environment' => 'sandbox',
    ], \Foundry\Services\PaymentProvider::getPublicKey($paypalConfig));

    $alipayConfig = ['provider' => \Foundry\Services\PaymentProvider::ALIPAY, 'app_id' => 'alipay_app_123'];
    $this->assertEquals([
        'public_key' => 'alipay_app_123',
        'environment' => null,
    ], \Foundry\Services\PaymentProvider::getPublicKey($alipayConfig));

    $genericConfig = ['provider' => 'custom', 'public_key' => 'custom_pk_123'];
    $this->assertEquals([
        'public_key' => 'custom_pk_123',
        'environment' => null,
    ], \Foundry\Services\PaymentProvider::getPublicKey($genericConfig));
});
