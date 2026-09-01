<?php

use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('can dynamically add and retrieve a payment provider via facade', function () {
    PaymentProvider::add('custom_crypto', [
        'name' => 'Crypto Pay',
        'label' => 'Pay with Crypto',
        'enabled' => true,
        'order' => 1,
        'public_key' => 'pk_crypto_123',
        'methods' => ['usdt', 'btc'],
    ]);

    $this->assertTrue(PaymentProvider::has('custom_crypto'));

    $config = PaymentProvider::find('custom_crypto');
    $this->assertNotNull($config);
    $this->assertEquals('custom_crypto', $config['provider']);
    $this->assertEquals('Crypto Pay', $config['name']);
    $this->assertEquals(['usdt', 'btc'], $config['methods']);

    $enabled = PaymentProvider::enabled();
    $this->assertTrue($enabled->has('custom_crypto'));

    $publicProviders = PaymentProvider::toPublic();
    $cryptoPublic = $publicProviders->firstWhere('provider', 'custom_crypto')->toArray();
    $this->assertNotNull($cryptoPublic);
    $this->assertEquals('Crypto Pay', $cryptoPublic['name']);
    $this->assertEquals('Pay with Crypto', $cryptoPublic['label']);
    $this->assertEquals('pk_crypto_123', $cryptoPublic['public_key']);
    $this->assertArrayNotHasKey('key', $cryptoPublic);
    $this->assertArrayNotHasKey('client_id', $cryptoPublic);
    $this->assertArrayNotHasKey('credentials', $cryptoPublic);

    PaymentProvider::remove('custom_crypto');
    $this->assertFalse(PaymentProvider::has('custom_crypto'));
    $this->assertNull(PaymentProvider::find('custom_crypto'));
});

it('can add a payment provider statically on registry class', function () {
    PaymentProvider::add('custom_bank', [
        'name' => 'Bank Direct',
        'enabled' => true,
    ]);

    $this->assertTrue(PaymentProvider::has('custom_bank'));
    $this->assertEquals('Bank Direct', PaymentProvider::find('custom_bank')['name']);

    PaymentProvider::remove('custom_bank');
    $this->assertFalse(PaymentProvider::has('custom_bank'));
});
