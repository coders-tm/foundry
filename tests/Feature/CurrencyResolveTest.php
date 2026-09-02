<?php

use Foundry\Models\ExchangeRate;
use Foundry\Services\Currency;
use Foundry\Tests\TestCase;
use Stevebauman\Location\Position;

uses(TestCase::class);

it('resolves currency from address country code', function () {
    ExchangeRate::updateOrCreate(['currency' => 'GBP'], [
        'name' => 'British Pound',
        'symbol' => '£',
        'rate' => 0.75,
    ]);

    $currency = new Currency;
    $currency->resolve(['country_code' => 'GB']);

    expect($currency->code())->toBe('GBP');
    expect($currency->rate())->toBe(0.75);
});

it('resolves currency from address country name', function () {
    ExchangeRate::updateOrCreate(['currency' => 'EUR'], [
        'name' => 'Euro',
        'symbol' => '€',
        'rate' => 0.85,
    ]);

    $currency = new Currency;
    $currency->resolve(['country' => 'Germany']);

    expect($currency->code())->toBe('EUR');
    expect($currency->rate())->toBe(0.85);
});

it('resolves currency from ip when address is missing', function () {
    ExchangeRate::updateOrCreate(['currency' => 'CAD'], [
        'name' => 'Canadian Dollar',
        'symbol' => '$',
        'rate' => 1.25,
    ]);

    $position = new Position;
    $position->countryCode = 'CA';

    $request = request();
    $request->attributes->set('ip_location', $position);

    $currency = new Currency;
    $currency->resolve([]);

    expect($currency->code())->toBe('CAD');
    expect($currency->rate())->toBe(1.25);
});

it('falls back to base currency if nothing resolves', function () {
    $currency = new Currency;
    request()->attributes->set('ip_location', null);

    $currency->resolve(['country_code' => 'XX']);

    expect($currency->code())->toBe('USD');
    expect($currency->rate())->toBe(1.0);
});
