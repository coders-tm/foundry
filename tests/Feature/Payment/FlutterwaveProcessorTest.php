<?php

use Foundry\Payment\Mappers\FlutterwavePayment;
use Foundry\Payment\Processor;
use Foundry\Payment\Processors\FlutterwaveProcessor;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\Feature\FeatureTestCase;

uses(FeatureTestCase::class);

beforeEach(function () {
    if (! env('FLUTTERWAVE_CLIENT_SECRET')) {
        $this->markTestSkipped('Flutterwave credentials not configured.');
    }
});

it('creates flutterwave payment method with correct configuration', function () {
    $provider = PaymentProvider::find('flutterwave');
    expect($provider)->not->toBeNull();
    expect($provider['provider'])->toBe('flutterwave');
    expect($provider['active'])->toBeTrue();
});

it('checks processor supports flutterwave', function () {
    expect(Processor::isSupported('flutterwave'))->toBeTrue();
});

it('creates flutterwave processor instance', function () {
    $processor = Processor::make('flutterwave');
    expect($processor)->toBeInstanceOf(FlutterwaveProcessor::class);
    expect($processor->getProvider())->toBe('flutterwave');
});

it('extracts card payment metadata from transaction', function () {
    $transaction = ['id' => 12345, 'tx_ref' => 'FLW-TEST-123', 'status' => 'successful', 'payment_type' => 'card', 'card' => ['first_6digits' => '539983', 'last_4digits' => '8381', 'issuer' => 'MASTERCARD', 'country' => 'NG', 'type' => 'VISA', 'expiry' => '09/32'], 'amount' => 1000, 'currency' => 'NGN'];
    $payment = new FlutterwavePayment($transaction);
    $metadata = $payment->getMetadata();
    expect($metadata['payment_method_type'])->toBe('card');
    expect($metadata['last_four'])->toBe('8381');
    expect($metadata['card_brand'])->toBe('VISA');
    expect($metadata['country'])->toBe('NG');
    expect($payment->toString())->toBe('VISA •••• 8381 (MASTERCARD)');
});

it('extracts mobile money metadata from transaction', function () {
    $transaction = ['id' => 12346, 'tx_ref' => 'FLW-MOBILEMONEY-123', 'status' => 'successful', 'payment_type' => 'mobilemoney', 'customer' => ['phone_number' => '+233123456789'], 'amount' => 500, 'currency' => 'GHS'];
    $payment = new FlutterwavePayment($transaction);
    $metadata = $payment->getMetadata();
    expect($metadata['payment_method_type'])->toBe('mobilemoney');
    expect($metadata['mobile_number'])->toBe('+233123456789');
    expect($payment->toString())->toBe('Mobilemoney (+233123456789)');
});

it('extracts ussd payment metadata from transaction', function () {
    $transaction = ['id' => 12347, 'tx_ref' => 'FLW-USSD-123', 'status' => 'successful', 'payment_type' => 'ussd', 'amount' => 2000, 'currency' => 'NGN'];
    $payment = new FlutterwavePayment($transaction);
    $metadata = $payment->getMetadata();
    expect($metadata['payment_method_type'])->toBe('ussd');
    expect($payment->toString())->toBe('Ussd');
});

it('extracts bank transfer metadata from transaction', function () {
    $transaction = ['id' => 12348, 'tx_ref' => 'FLW-TEST-012', 'status' => 'successful', 'payment_type' => 'banktransfer', 'account' => ['bank_code' => 'ACCESS', 'account_number' => '0123456789'], 'amount' => 5000, 'currency' => 'NGN'];
    $payment = new FlutterwavePayment($transaction);
    $metadata = $payment->getMetadata();
    expect($metadata['payment_method_type'])->toBe('banktransfer');
    expect($payment->toString())->toBe('Banktransfer');
});
