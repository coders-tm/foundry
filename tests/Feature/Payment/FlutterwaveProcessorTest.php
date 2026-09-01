<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class);

beforeEach(function () {
    if (! env('FLUTTERWAVE_CLIENT_SECRET')) {
        $this->markTestSkipped('Flutterwave credentials not configured.');
    }
});

it('creates flutterwave payment method with correct configuration', function () {
    $provider = \Foundry\Services\PaymentProvider::find('flutterwave');
    $this->assertNotNull($provider);
    $this->assertEquals('flutterwave', $provider['provider']);
    $this->assertTrue($provider['active']);
});

it('checks processor supports flutterwave', function () {
    $this->assertTrue(\Foundry\Payment\Processor::isSupported('flutterwave'));
});

it('creates flutterwave processor instance', function () {
    $processor = \Foundry\Payment\Processor::make('flutterwave');
    $this->assertInstanceOf(\Foundry\Payment\Processors\FlutterwaveProcessor::class, $processor);
    $this->assertEquals('flutterwave', $processor->getProvider());
});

it('extracts card payment metadata from transaction', function () {
    $transaction = ['id' => 12345, 'tx_ref' => 'FLW-TEST-123', 'status' => 'successful', 'payment_type' => 'card', 'card' => ['first_6digits' => '539983', 'last_4digits' => '8381', 'issuer' => 'MASTERCARD', 'country' => 'NG', 'type' => 'VISA', 'expiry' => '09/32'], 'amount' => 1000, 'currency' => 'NGN'];
    $payment = new \Foundry\Payment\Mappers\FlutterwavePayment($transaction);
    $metadata = $payment->getMetadata();
    $this->assertEquals('card', $metadata['payment_method_type']);
    $this->assertEquals('8381', $metadata['last_four']);
    $this->assertEquals('VISA', $metadata['card_brand']);
    $this->assertEquals('NG', $metadata['country']);
    $this->assertEquals('VISA •••• 8381 (MASTERCARD)', $payment->toString());
});

it('extracts mobile money metadata from transaction', function () {
    $transaction = ['id' => 12346, 'tx_ref' => 'FLW-MOBILEMONEY-123', 'status' => 'successful', 'payment_type' => 'mobilemoney', 'customer' => ['phone_number' => '+233123456789'], 'amount' => 500, 'currency' => 'GHS'];
    $payment = new \Foundry\Payment\Mappers\FlutterwavePayment($transaction);
    $metadata = $payment->getMetadata();
    $this->assertEquals('mobilemoney', $metadata['payment_method_type']);
    $this->assertEquals('+233123456789', $metadata['mobile_number']);
    $this->assertEquals('Mobilemoney (+233123456789)', $payment->toString());
});

it('extracts ussd payment metadata from transaction', function () {
    $transaction = ['id' => 12347, 'tx_ref' => 'FLW-USSD-123', 'status' => 'successful', 'payment_type' => 'ussd', 'amount' => 2000, 'currency' => 'NGN'];
    $payment = new \Foundry\Payment\Mappers\FlutterwavePayment($transaction);
    $metadata = $payment->getMetadata();
    $this->assertEquals('ussd', $metadata['payment_method_type']);
    $this->assertEquals('Ussd', $payment->toString());
});

it('extracts bank transfer metadata from transaction', function () {
    $transaction = ['id' => 12348, 'tx_ref' => 'FLW-TEST-012', 'status' => 'successful', 'payment_type' => 'banktransfer', 'account' => ['bank_code' => 'ACCESS', 'account_number' => '0123456789'], 'amount' => 5000, 'currency' => 'NGN'];
    $payment = new \Foundry\Payment\Mappers\FlutterwavePayment($transaction);
    $metadata = $payment->getMetadata();
    $this->assertEquals('banktransfer', $metadata['payment_method_type']);
    $this->assertEquals('Banktransfer', $payment->toString());
});
