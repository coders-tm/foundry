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
    expect($processor->supportedCurrencies())->not->toBeEmpty();
    expect($processor->supportedCurrencies())->toContain('USD');
});

it('paypal processor has supported currencies', function () {
    $processor = new PaypalProcessor;
    expect($processor->supportedCurrencies())->not->toBeEmpty();
    expect($processor->supportedCurrencies())->toContain('USD');
});

it('klarna processor has supported currencies', function () {
    $processor = new KlarnaProcessor;
    expect($processor->supportedCurrencies())->not->toBeEmpty();
    expect($processor->supportedCurrencies())->toContain('USD');
});

it('xendit processor has supported currencies', function () {
    $processor = new XenditProcessor;
    expect($processor->supportedCurrencies())->not->toBeEmpty();
    expect($processor->supportedCurrencies())->toContain('IDR');
});

it('paystack processor has supported currencies', function () {
    $processor = new PaystackProcessor;
    expect($processor->supportedCurrencies())->not->toBeEmpty();
    expect($processor->supportedCurrencies())->toContain('NGN');
});

it('razorpay processor has supported currencies', function () {
    $processor = new RazorpayProcessor;
    expect($processor->supportedCurrencies())->not->toBeEmpty();
    expect($processor->supportedCurrencies())->toContain('INR');
});

it('flutterwave processor has supported currencies', function () {
    $processor = new FlutterwaveProcessor;
    expect($processor->supportedCurrencies())->not->toBeEmpty();
    expect($processor->supportedCurrencies())->toContain('NGN');
});

it('mercadopago processor has supported currencies', function () {
    $processor = new MercadoPagoProcessor;
    expect($processor->supportedCurrencies())->not->toBeEmpty();
    expect($processor->supportedCurrencies())->toContain('BRL');
});

it('manual processor supports all currencies', function () {
    $processor = new ManualProcessor;
    expect($processor->supportedCurrencies())->toBeEmpty();
});

it('wallet processor supports all currencies', function () {
    $processor = new WalletProcessor;
    expect($processor->supportedCurrencies())->toBeEmpty();
});
