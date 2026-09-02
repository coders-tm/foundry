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
    expect($rate)->toEqual(83.0);
});

it('exchange rate returns one for base currency', function () {
    $rate = ExchangeRate::rateFor('USD');
    expect($rate)->toEqual(1.0);
});

it('exchange rate converts amount correctly', function () {
    $converted = ExchangeRate::convertAmount(100.00, 'USD', 'INR');
    expect($converted)->toEqual(8300.00);

    $converted = ExchangeRate::convertAmount(8300.00, 'INR', 'USD');
    expect($converted)->toEqual(100.00);

    $converted = ExchangeRate::convertAmount(100.00, 'USD', 'USD');
    expect($converted)->toEqual(100.00);
});

it('order stores base values only', function () {
    $order = Order::factory()->create([
        'grand_total' => 100.00,
        'sub_total' => 80.00,
        'tax_total' => 10.00,
        'status' => 'pending',
    ]);

    expect($order->grand_total)->toEqual(100.00);
    expect($order->sub_total)->toEqual(80.00);

    expect($order->currency ?? null)->toBeNull();
    expect($order->exchange_rate ?? null)->toBeNull();
});

it('get currency from country code', function () {
    $currency = ExchangeRate::getCurrencyFromCountryCode('IN');
    expect($currency)->toBe('INR');

    $currency = ExchangeRate::getCurrencyFromCountryCode('US');
    expect($currency)->toBe('USD');

    $currency = ExchangeRate::getCurrencyFromCountryCode('XX');
    expect($currency)->toBe('USD');
});
