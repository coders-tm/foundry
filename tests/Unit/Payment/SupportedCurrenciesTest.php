<?php

uses(Foundry\Tests\BaseTestCase::class)->use(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('stripe processor has supported currencies', function () {
    $processor = new \Foundry\Payment\Processors\StripeProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('USD', $processor->supportedCurrencies());
});

it('paypal processor has supported currencies', function () {
    $processor = new \Foundry\Payment\Processors\PaypalProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('USD', $processor->supportedCurrencies());
});

it('klarna processor has supported currencies', function () {
    $processor = new \Foundry\Payment\Processors\KlarnaProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('USD', $processor->supportedCurrencies());
});

it('xendit processor has supported currencies', function () {
    $processor = new \Foundry\Payment\Processors\XenditProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('IDR', $processor->supportedCurrencies());
});

it('paystack processor has supported currencies', function () {
    $processor = new \Foundry\Payment\Processors\PaystackProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('NGN', $processor->supportedCurrencies());
});

it('razorpay processor has supported currencies', function () {
    $processor = new \Foundry\Payment\Processors\RazorpayProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('INR', $processor->supportedCurrencies());
});

it('flutterwave processor has supported currencies', function () {
    $processor = new \Foundry\Payment\Processors\FlutterwaveProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('NGN', $processor->supportedCurrencies());
});

it('mercadopago processor has supported currencies', function () {
    $processor = new \Foundry\Payment\Processors\MercadoPagoProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('BRL', $processor->supportedCurrencies());
});

it('manual processor supports all currencies', function () {
    $processor = new \Foundry\Payment\Processors\ManualProcessor;
    $this->assertEmpty($processor->supportedCurrencies());
});

it('wallet processor supports all currencies', function () {
    $processor = new \Foundry\Payment\Processors\WalletProcessor;
    $this->assertEmpty($processor->supportedCurrencies());
});
