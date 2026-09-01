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

    $this->assertEquals('GBP', $currency->code());
    $this->assertEquals(0.75, $currency->rate());
});

it('resolves currency from address country name', function () {
    ExchangeRate::updateOrCreate(['currency' => 'EUR'], [
        'name' => 'Euro',
        'symbol' => '€',
        'rate' => 0.85,
    ]);

    $currency = new Currency;
    $currency->resolve(['country' => 'Germany']);

    $this->assertEquals('EUR', $currency->code());
    $this->assertEquals(0.85, $currency->rate());
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

    $this->assertEquals('CAD', $currency->code());
    $this->assertEquals(1.25, $currency->rate());
});

it('falls back to base currency if nothing resolves', function () {
    $currency = new Currency;
    request()->attributes->set('ip_location', null);

    $currency->resolve(['country_code' => 'XX']);

    $this->assertEquals('USD', $currency->code());
    $this->assertEquals(1.0, $currency->rate());
});
