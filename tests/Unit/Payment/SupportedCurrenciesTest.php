<?php

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
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(BaseTestCase::class)->use(RefreshDatabase::class);

it('stripe processor has supported currencies', function () {
    $processor = new StripeProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('USD', $processor->supportedCurrencies());
});

it('paypal processor has supported currencies', function () {
    $processor = new PaypalProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('USD', $processor->supportedCurrencies());
});

it('klarna processor has supported currencies', function () {
    $processor = new KlarnaProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('USD', $processor->supportedCurrencies());
});

it('xendit processor has supported currencies', function () {
    $processor = new XenditProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('IDR', $processor->supportedCurrencies());
});

it('paystack processor has supported currencies', function () {
    $processor = new PaystackProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('NGN', $processor->supportedCurrencies());
});

it('razorpay processor has supported currencies', function () {
    $processor = new RazorpayProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('INR', $processor->supportedCurrencies());
});

it('flutterwave processor has supported currencies', function () {
    $processor = new FlutterwaveProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('NGN', $processor->supportedCurrencies());
});

it('mercadopago processor has supported currencies', function () {
    $processor = new MercadoPagoProcessor;
    $this->assertNotEmpty($processor->supportedCurrencies());
    $this->assertContains('BRL', $processor->supportedCurrencies());
});

it('manual processor supports all currencies', function () {
    $processor = new ManualProcessor;
    $this->assertEmpty($processor->supportedCurrencies());
});

it('wallet processor supports all currencies', function () {
    $processor = new WalletProcessor;
    $this->assertEmpty($processor->supportedCurrencies());
});
