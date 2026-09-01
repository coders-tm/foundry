<?php

uses(Foundry\Tests\TestCase::class)->use(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Config::set('app.currency', 'USD');

    \Foundry\Models\ExchangeRate::updateOrCreate(
        ['currency' => 'INR'],
        ['rate' => 83.0]
    );
});

it('exchange rate returns correct rate', function () {
    $rate = \Foundry\Models\ExchangeRate::rateFor('INR');
    $this->assertEquals(83.0, $rate);
});

it('exchange rate returns one for base currency', function () {
    $rate = \Foundry\Models\ExchangeRate::rateFor('USD');
    $this->assertEquals(1.0, $rate);
});

it('exchange rate converts amount correctly', function () {
    $converted = \Foundry\Models\ExchangeRate::convertAmount(100.00, 'USD', 'INR');
    $this->assertEquals(8300.00, $converted);

    $converted = \Foundry\Models\ExchangeRate::convertAmount(8300.00, 'INR', 'USD');
    $this->assertEquals(100.00, $converted);

    $converted = \Foundry\Models\ExchangeRate::convertAmount(100.00, 'USD', 'USD');
    $this->assertEquals(100.00, $converted);
});

it('order stores base values only', function () {
    $order = \Foundry\Models\Order::factory()->create([
        'grand_total' => 100.00,
        'sub_total' => 80.00,
        'tax_total' => 10.00,
        'status' => 'pending',
    ]);

    $this->assertEquals(100.00, $order->grand_total);
    $this->assertEquals(80.00, $order->sub_total);

    $this->assertNull($order->currency ?? null);
    $this->assertNull($order->exchange_rate ?? null);
});

it('get currency from country code', function () {
    $currency = \Foundry\Models\ExchangeRate::getCurrencyFromCountryCode('IN');
    $this->assertEquals('INR', $currency);

    $currency = \Foundry\Models\ExchangeRate::getCurrencyFromCountryCode('US');
    $this->assertEquals('USD', $currency);

    $currency = \Foundry\Models\ExchangeRate::getCurrencyFromCountryCode('XX');
    $this->assertEquals('USD', $currency);
});
