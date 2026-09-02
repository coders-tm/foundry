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

    expect(PaymentProvider::has('custom_crypto'))->toBeTrue();

    $config = PaymentProvider::find('custom_crypto');
    expect($config)->not->toBeNull();
    expect($config['provider'])->toBe('custom_crypto');
    expect($config['name'])->toBe('Crypto Pay');
    expect($config['methods'])->toBe(['usdt', 'btc']);

    $enabled = PaymentProvider::enabled();
    expect($enabled->has('custom_crypto'))->toBeTrue();

    $publicProviders = PaymentProvider::toPublic();
    $cryptoPublic = $publicProviders->firstWhere('provider', 'custom_crypto')->toArray();
    expect($cryptoPublic)->not->toBeNull();
    expect($cryptoPublic['name'])->toBe('Crypto Pay');
    expect($cryptoPublic['label'])->toBe('Pay with Crypto');
    expect($cryptoPublic['public_key'])->toBe('pk_crypto_123');
    expect($cryptoPublic)->not->toHaveKey('key');
    expect($cryptoPublic)->not->toHaveKey('client_id');
    expect($cryptoPublic)->not->toHaveKey('credentials');

    PaymentProvider::remove('custom_crypto');
    expect(PaymentProvider::has('custom_crypto'))->toBeFalse();
    expect(PaymentProvider::find('custom_crypto'))->toBeNull();
});

it('can add a payment provider statically on registry class', function () {
    PaymentProvider::add('custom_bank', [
        'name' => 'Bank Direct',
        'enabled' => true,
    ]);

    expect(PaymentProvider::has('custom_bank'))->toBeTrue();
    expect(PaymentProvider::find('custom_bank')['name'])->toBe('Bank Direct');

    PaymentProvider::remove('custom_bank');
    expect(PaymentProvider::has('custom_bank'))->toBeFalse();
});
