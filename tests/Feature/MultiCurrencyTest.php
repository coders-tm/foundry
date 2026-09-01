<?php

use Foundry\Models\ExchangeRate;
use Foundry\Models\Order;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function () {
    Config::set('app.currency', 'USD');

    ExchangeRate::updateOrCreate(
        ['currency' => 'INR'],
        ['rate' => 83.0]
    );
});

it('exchange rate returns correct rate', function () {
    $rate = ExchangeRate::rateFor('INR');
    $this->assertEquals(83.0, $rate);
});

it('exchange rate returns one for base currency', function () {
    $rate = ExchangeRate::rateFor('USD');
    $this->assertEquals(1.0, $rate);
});

it('exchange rate converts amount correctly', function () {
    $converted = ExchangeRate::convertAmount(100.00, 'USD', 'INR');
    $this->assertEquals(8300.00, $converted);

    $converted = ExchangeRate::convertAmount(8300.00, 'INR', 'USD');
    $this->assertEquals(100.00, $converted);

    $converted = ExchangeRate::convertAmount(100.00, 'USD', 'USD');
    $this->assertEquals(100.00, $converted);
});

it('order stores base values only', function () {
    $order = Order::factory()->create([
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
    $currency = ExchangeRate::getCurrencyFromCountryCode('IN');
    $this->assertEquals('INR', $currency);

    $currency = ExchangeRate::getCurrencyFromCountryCode('US');
    $this->assertEquals('USD', $currency);

    $currency = ExchangeRate::getCurrencyFromCountryCode('XX');
    $this->assertEquals('USD', $currency);
});
