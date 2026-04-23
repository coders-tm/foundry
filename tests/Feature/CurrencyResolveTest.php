<?php

namespace Tests\Feature;

use Foundry\Models\ExchangeRate;
use Foundry\Services\Currency;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Stevebauman\Location\Position;

class CurrencyResolveTest extends TestCase
{
    #[Test]
    public function it_resolves_currency_from_address_country_code()
    {
        // Setup existing exchange rate
        ExchangeRate::updateOrCreate(['currency' => 'GBP'], [
            'name' => 'British Pound',
            'symbol' => '£',
            'rate' => 0.75,
        ]);

        // Mock static method on ExchangeRate?
        // Or rely on real database since it's a feature test?
        // Let's use real DB but we need to seed the mapping if getCurrencyFromCountryCode hits DB or config.
        // Assuming ExchangeRate::getCurrencyFromCountryCode logic: if it uses a mapping array, we might need to mock it or ensure data.
        // Let's look at getCurrencyFromCountryCode logic later if it fails.
        // For now, assuming standard behavior where country code 'GB' maps to 'GBP'.

        $currency = new Currency;
        $currency->resolve(['country_code' => 'GB']);

        $this->assertEquals('GBP', $currency->code());
        $this->assertEquals(0.75, $currency->rate());
    }

    #[Test]
    public function it_resolves_currency_from_address_country_name()
    {
        ExchangeRate::updateOrCreate(['currency' => 'EUR'], [
            'name' => 'Euro',
            'symbol' => '€',
            'rate' => 0.85,
        ]);

        $currency = new Currency;
        $currency->resolve(['country' => 'Germany']);

        // Assuming 'Germany' maps to 'EUR'
        $this->assertEquals('EUR', $currency->code());
        $this->assertEquals(0.85, $currency->rate());
    }

    #[Test]
    public function it_resolves_currency_from_ip_when_address_is_missing()
    {
        ExchangeRate::updateOrCreate(['currency' => 'CAD'], [
            'name' => 'Canadian Dollar',
            'symbol' => '$',
            'rate' => 1.25,
        ]);

        // Mock Location
        $position = new Position;
        $position->countryCode = 'CA';

        // Mock Request Attribute
        $request = request();
        $request->attributes->set('ip_location', $position);

        $currency = new Currency;
        $currency->resolve([]);

        $this->assertEquals('CAD', $currency->code());
        $this->assertEquals(1.25, $currency->rate());
    }

    #[Test]
    public function it_falls_back_to_base_currency_if_nothing_resolves()
    {
        $currency = new Currency;
        // Mock empty request or empty attribute
        request()->attributes->set('ip_location', null);

        $currency->resolve(['country_code' => 'XX']);

        $this->assertEquals('USD', $currency->code()); // Assuming USD is base
        $this->assertEquals(1.0, $currency->rate());
    }
}
