<?php

uses(Foundry\Tests\TestCase::class);

it('resolves currency from address country code', function () {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'GBP'], [
        'name' => 'British Pound',
        'symbol' => '£',
        'rate' => 0.75,
    ]);

    $currency = new \Foundry\Services\Currency;
    $currency->resolve(['country_code' => 'GB']);

    $this->assertEquals('GBP', $currency->code());
    $this->assertEquals(0.75, $currency->rate());
});

it('resolves currency from address country name', function () {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'EUR'], [
        'name' => 'Euro',
        'symbol' => '€',
        'rate' => 0.85,
    ]);

    $currency = new \Foundry\Services\Currency;
    $currency->resolve(['country' => 'Germany']);

    $this->assertEquals('EUR', $currency->code());
    $this->assertEquals(0.85, $currency->rate());
});

it('resolves currency from ip when address is missing', function () {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'CAD'], [
        'name' => 'Canadian Dollar',
        'symbol' => '$',
        'rate' => 1.25,
    ]);

    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'CA';

    $request = request();
    $request->attributes->set('ip_location', $position);

    $currency = new \Foundry\Services\Currency;
    $currency->resolve([]);

    $this->assertEquals('CAD', $currency->code());
    $this->assertEquals(1.25, $currency->rate());
});

it('falls back to base currency if nothing resolves', function () {
    $currency = new \Foundry\Services\Currency;
    request()->attributes->set('ip_location', null);

    $currency->resolve(['country_code' => 'XX']);

    $this->assertEquals('USD', $currency->code());
    $this->assertEquals(1.0, $currency->rate());
});
