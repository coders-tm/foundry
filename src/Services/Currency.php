<?php

namespace Foundry\Services;

use Foundry\Contracts\Currencyable;
use Foundry\Models\ExchangeRate;
use Illuminate\Support\Collection;

/**
 * Request-scoped currency service.
 * Holds the resolved currency and exchange rate for the current request.
 * Set by ResolveCurrency middleware or manually via initialize().
 */
class Currency
{
    protected string $code;

    protected float $rate;

    public function __construct()
    {
        $this->code = config('app.currency', 'USD');
        $this->rate = 1.0;
    }

    /**
     * Get the current currency code.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Get the current exchange rate.
     */
    public function rate(): float
    {
        return $this->rate;
    }

    /**
     * Convert amount from base currency to current currency.
     *
     * @param  float  $amount  Amount in base currency
     * @return float Converted amount in current currency
     */
    public function convert(?float $amount): float
    {
        return round(((float) $amount) * $this->rate, 2);
    }

    /**
     * Convert and format amount.
     *
     * @param  float  $amount  Amount in base currency
     * @return string Formatted amount in current currency
     */
    public function format(?float $amount): string
    {
        $converted = $this->convert($amount);

        return format_amount($converted, $this->code);
    }

    /**
     * Convert multiple price fields from model/array and add currency info.
     *
     * @param  mixed  $data  Model, array, or object with price fields
     * @param  array  $fields  Field names to convert (e.g., ['price', 'total', 'discount'])
     * @return array Converted prices with currency code
     */
    public function toArray($data, array $fields): array
    {
        $result = ['currency' => $this->code];

        foreach ($fields as $field) {
            $value = is_array($data) ? ($data[$field] ?? null) : ($data->$field ?? null);

            if ($value !== null) {
                $result[$field] = $this->convert((float) $value);
            }
        }

        return array_merge($data->toArray(), $result);
    }

    /**
     * Transform a model or collection automatically based on Currencyable interface.
     * If model implements Currencyable, uses its getCurrencyFields() to determine which fields to convert.
     *
     * @param  mixed  $data  Model, Collection, array, or iterable
     * @return mixed Transformed data (array or Collection)
     */
    public function transform($data)
    {
        // Handle collections/arrays
        if (is_iterable($data) && ! ($data instanceof Currencyable)) {
            $isCollection = $data instanceof Collection;
            $transformed = [];

            foreach ($data as $key => $item) {
                $transformed[$key] = $this->transformSingle($item);
            }

            return $isCollection ? collect($transformed) : $transformed;
        }

        // Handle single model/item
        return $this->transformSingle($data);
    }

    /**
     * Transform a single item.
     *
     * @param  mixed  $item
     */
    protected function transformSingle($item): array
    {
        // If item implements Currencyable, use its defined fields
        if ($item instanceof Currencyable) {
            return $this->toArray($item, $item->getCurrencyFields());
        }

        // Otherwise, just convert to array and add currency code
        $array = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);

        return array_merge($array, ['currency' => $this->code]);
    }

    /**
     * Set currency code and exchange rate manually.
     *
     * @param  string  $code  Currency code (e.g., 'USD', 'EUR')
     * @param  float  $rate  Exchange rate from base currency
     */
    public function set(string $code, float $rate): self
    {
        $this->code = strtoupper($code);
        $this->rate = (float) $rate;

        return $this;
    }

    /**
     * Initialize currency with automatic exchange rate lookup.
     * Falls back to base currency if rate is not found (no error thrown).
     *
     * @param  string|null  $code  Currency code (e.g., 'INR', 'EUR')
     */
    public function initialize(?string $code = null): self
    {
        $baseCode = self::base();
        $code = strtoupper($code ?? $baseCode);

        // If requesting base currency, no need to look up rate
        if ($code === $baseCode) {
            return $this->set($baseCode, 1.0);
        }

        try {
            $rate = ExchangeRate::rateFor($code);

            return $this->set($code, $rate);
        } catch (\Throwable $e) {
            // Fallback to base currency if rate not found
            return $this->set($baseCode, 1.0);
        }
    }

    /**
     * Resolve and initialize currency from address or IP.
     *
     * @param  array  $address  Array with 'country_code' or 'country' keys
     */
    public function resolve(array $address = []): self
    {
        $currencyCode = null;

        // 1. Try to resolve from address (Country Code)
        if (! empty($address['country_code'])) {
            $currencyCode = ExchangeRate::getCurrencyFromCountryCode($address['country_code']);
        }

        // 2. Try to resolve from address (Country Name)
        if (! $currencyCode && ! empty($address['country'])) {
            $currencyCode = ExchangeRate::getCurrencyFromCountry($address['country']);
        }

        // 3. Check Cloudflare CF-IPCountry header (if behind Cloudflare proxy)
        if (! $currencyCode) {
            $cfCountry = request()->header('CF-IPCountry');

            if ($cfCountry && $cfCountry !== 'XX') { // XX means country detection failed
                $currencyCode = ExchangeRate::getCurrencyFromCountryCode($cfCountry);
            }
        }

        // 4. Fallback to IP geolocation (via ResolveIpAddress middleware)
        if (! $currencyCode) {
            $countryCode = request()->ipLocation('countryCode');

            if ($countryCode) {
                $currencyCode = ExchangeRate::getCurrencyFromCountryCode($countryCode);
            }
        }

        return $this->initialize($currencyCode);
    }

    /**
     * Revert to base currency.
     */
    public function revert(): self
    {
        return $this->set(self::base(), 1.0);
    }

    /**
     * Check if current currency is the base currency.
     */
    public function isBase(): bool
    {
        return $this->code === self::base();
    }

    /**
     * Get the base currency code.
     */
    public static function base(): string
    {
        return strtoupper(config('app.currency', 'USD'));
    }
}
