<?php

namespace Foundry\Tests\Feature;

use Foundry\Models\ExchangeRate;
use Foundry\Models\Order;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class MultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected $baseCurrency = 'USD';

    protected $testCurrency = 'INR';

    protected $exchangeRate = 83.0;

    protected function setUp(): void
    {
        parent::setUp();

        // Set base currency
        Config::set('app.currency', $this->baseCurrency);

        // Create exchange rate
        ExchangeRate::updateOrCreate(
            ['currency' => $this->testCurrency],
            ['rate' => $this->exchangeRate]
        );
    }

    #[Test]
    public function test_exchange_rate_returns_correct_rate()
    {
        $rate = ExchangeRate::rateFor($this->testCurrency);
        $this->assertEquals($this->exchangeRate, $rate);
    }

    #[Test]
    public function test_exchange_rate_returns_one_for_base_currency()
    {
        $rate = ExchangeRate::rateFor($this->baseCurrency);
        $this->assertEquals(1.0, $rate);
    }

    #[Test]
    public function test_exchange_rate_converts_amount_correctly()
    {
        // Convert Base to Target: 100 * 83 = 8300
        $converted = ExchangeRate::convertAmount(100.00, $this->baseCurrency, $this->testCurrency);
        $this->assertEquals(8300.00, $converted);

        // Convert Target to Base: 8300 / 83 = 100
        $converted = ExchangeRate::convertAmount(8300.00, $this->testCurrency, $this->baseCurrency);
        $this->assertEquals(100.00, $converted);

        // Convert Base to Base: 100 -> 100
        $converted = ExchangeRate::convertAmount(100.00, $this->baseCurrency, $this->baseCurrency);
        $this->assertEquals(100.00, $converted);
    }

    #[Test]
    public function test_order_stores_base_values_only()
    {
        $order = Order::create([
            'grand_total' => 100.00,
            'sub_total' => 80.00,
            'tax_total' => 10.00,
            'status' => 'pending',
        ]);

        // Should return base value
        $this->assertEquals(100.00, $order->grand_total);
        $this->assertEquals(80.00, $order->sub_total);

        // Ensure no currency/exchange_rate fields
        $this->assertNull($order->currency ?? null);
        $this->assertNull($order->exchange_rate ?? null);
    }

    #[Test]
    public function test_get_currency_from_country_code()
    {
        // This relies on league/iso3166 package being present and working
        $currency = ExchangeRate::getCurrencyFromCountryCode('IN');
        $this->assertEquals('INR', $currency);

        $currency = ExchangeRate::getCurrencyFromCountryCode('US');
        $this->assertEquals('USD', $currency);

        // Invalid/Unknown -> Base Currency
        $currency = ExchangeRate::getCurrencyFromCountryCode('XX');
        $this->assertEquals($this->baseCurrency, $currency);
    }
}
