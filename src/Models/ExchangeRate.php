<?php

namespace Foundry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use League\ISO3166\ISO3166;

class ExchangeRate extends Model
{
    use HasUuids;

    protected $fillable = [
        'currency',
        'rate',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
    ];

    public static function getBaseCurrency(): string
    {
        return strtoupper(config('app.currency', 'USD'));
    }

    /**
     * Get the rate for a specific currency.
     * Returns 1 if currency is the same as base or not found (default behavior can be adjusted).
     */
    public static function rateFor(string $currency): float
    {
        $currency = strtoupper($currency);

        if ($currency === self::getBaseCurrency()) {
            return 1.0;
        }

        $rate = static::where('currency', $currency)->value('rate');

        if (is_null($rate)) {
            throw new \RuntimeException("Missing exchange rate for currency: {$currency}");
        }

        return (float) $rate;
    }

    public static function getRate(string $currency): float
    {
        return self::rateFor($currency);
    }

    public static function convertAmount(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);
        $baseCurrency = self::getBaseCurrency();

        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        if ($fromCurrency !== $baseCurrency) {
            $fromRate = self::rateFor($fromCurrency);
            if ($fromRate > 0) {
                $amount = $amount / $fromRate;
            }
        }

        if ($toCurrency !== $baseCurrency) {
            $toRate = self::rateFor($toCurrency);
            if ($toRate > 0) {
                $amount = $amount * $toRate;
            }
        }

        return $amount;
    }

    /**
     * Get currency code from country code.
     */
    public static function getCurrencyFromCountryCode(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        try {
            $data = (new ISO3166)->alpha2($countryCode);
            if (! empty($data['currency'][0])) {
                return strtoupper($data['currency'][0]);
            }
        } catch (\Throwable $e) {
            // Invalid country code or other error, fallback to default
        }

        return self::getBaseCurrency();
    }

    /**
     * Get currency code from country code.
     */
    public static function getCurrencyFromCountry(string $country): string
    {
        try {
            $data = (new ISO3166)->name($country);
            if (! empty($data['currency'][0])) {
                return strtoupper($data['currency'][0]);
            }
        } catch (\Throwable $e) {
            // Invalid country code or other error, fallback to default
        }

        return self::getBaseCurrency();
    }
}
