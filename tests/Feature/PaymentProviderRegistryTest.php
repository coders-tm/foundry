<?php

uses(Foundry\Tests\TestCase::class);

it('can dynamically add and retrieve a payment provider via facade', function () {
    \Foundry\Services\PaymentProvider::add('custom_crypto', [
        'name' => 'Crypto Pay',
        'label' => 'Pay with Crypto',
        'enabled' => true,
        'order' => 1,
        'public_key' => 'pk_crypto_123',
        'methods' => ['usdt', 'btc'],
    ]);

    $this->assertTrue(\Foundry\Services\PaymentProvider::has('custom_crypto'));

    $config = \Foundry\Services\PaymentProvider::find('custom_crypto');
    $this->assertNotNull($config);
    $this->assertEquals('custom_crypto', $config['provider']);
    $this->assertEquals('Crypto Pay', $config['name']);
    $this->assertEquals(['usdt', 'btc'], $config['methods']);

    $enabled = \Foundry\Services\PaymentProvider::enabled();
    $this->assertTrue($enabled->has('custom_crypto'));

    $publicProviders = \Foundry\Services\PaymentProvider::toPublic();
    $cryptoPublic = $publicProviders->firstWhere('provider', 'custom_crypto');
    $this->assertNotNull($cryptoPublic);
    $this->assertEquals('Crypto Pay', $cryptoPublic['name']);
    $this->assertEquals('Pay with Crypto', $cryptoPublic['label']);
    $this->assertEquals('pk_crypto_123', $cryptoPublic['public_key']);
    $this->assertArrayNotHasKey('key', $cryptoPublic);
    $this->assertArrayNotHasKey('client_id', $cryptoPublic);
    $this->assertArrayNotHasKey('credentials', $cryptoPublic);

    \Foundry\Services\PaymentProvider::remove('custom_crypto');
    $this->assertFalse(\Foundry\Services\PaymentProvider::has('custom_crypto'));
    $this->assertNull(\Foundry\Services\PaymentProvider::find('custom_crypto'));
});

it('can add a payment provider statically on registry class', function () {
    \Foundry\Services\PaymentProvider::add('custom_bank', [
        'name' => 'Bank Direct',
        'enabled' => true,
    ]);

    $this->assertTrue(\Foundry\Services\PaymentProvider::has('custom_bank'));
    $this->assertEquals('Bank Direct', \Foundry\Services\PaymentProvider::find('custom_bank')['name']);

    \Foundry\Services\PaymentProvider::remove('custom_bank');
    $this->assertFalse(\Foundry\Services\PaymentProvider::has('custom_bank'));
});
