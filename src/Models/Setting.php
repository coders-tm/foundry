<?php

namespace Foundry\Models;

use Foundry\Concerns\Core;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    use Core;

    protected $fillable = [
        'key',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    protected $logIgnore = [
        'options',
    ];

    /**
     * Cache key for all settings
     */
    protected static $cacheKey = 'foundry_settings';

    /**
     * Get the default override map with all handlers
     */
    protected static function getOverrideMap(): array
    {
        return config('foundry.settings_override', [
            'config' => [
                'alias' => 'app',
                'email' => [
                    'foundry.admin_email',
                    'mail.from.address',
                ],
                'name' => ['mail.from.name'],
                'currency' => 'stripe.currency',
                'timezone' => fn ($value) => date_default_timezone_set($value),
            ],
        ]);
    }

    /**
     * Create a new setting and return only its options
     *
     * @param  string  $key
     * @return array
     */
    public static function create($key, array $options = [])
    {
        $result = static::updateValue($key, $options);
        static::clearCache();

        return $result;
    }

    /**
     * Parse a dotted key into database key and nested path
     *
     * @return array ['key' => 'foo', 'path' => ['bar', 'baz']]
     */
    protected static function parseDottedKey(string $key): array
    {
        $segments = explode('.', $key);
        $dbKey = array_shift($segments);

        return [
            'key' => $dbKey,
            'path' => $segments,
        ];
    }

    /**
     * Set a nested value in an array using dot notation path
     *
     * @param  mixed  $value
     */
    protected static function setNestedValue(array $array, array $path, $value): array
    {
        if (empty($path)) {
            return is_array($value) ? $value : $array;
        }

        $current = &$array;
        foreach ($path as $segment) {
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        if (is_array($value)) {
            $current = array_merge($current, $value);
        } else {
            $current = $value;
        }

        return $array;
    }

    /**
     * Update a setting value and return only its options
     *
     * @param  string  $key
     * @return array
     *
     * @deprecated Use updateValue() instead.
     */
    public static function updateOptions($key, array $options = [])
    {
        return static::updateValue($key, $options);
    }

    /**
     * Update a setting value and return only its options
     *
     * @param  string  $key
     * @return array
     */
    public static function updateValue($key, array $options = [], bool $replace = false)
    {
        // Parse dotted key (e.g., 'foo.bar' -> key='foo', path=['bar'])
        $parsed = static::parseDottedKey($key);
        $dbKey = $parsed['key'];
        $path = $parsed['path'];

        // Get original settings for this key
        $original = $replace ? [] : static::findByKey($dbKey);

        // If we have a nested path, set the value at that path
        if (! empty($path)) {
            $newOptions = static::setNestedValue($original, $path, $options);
        } else {
            $newOptions = array_merge($original, $options);
        }

        // Filter out only truly empty values (empty arrays), but keep false, null, 0, ''
        $filteredOptions = static::filterEmptyArrays($newOptions);

        // Update or create the setting
        $model = static::updateOrCreate([
            'key' => $dbKey,
        ], [
            'options' => $filteredOptions,
        ]);

        // Update cache efficiently for just this key instead of clearing all
        if (Cache::has(static::$cacheKey)) {
            $cachedSettings = Cache::get(static::$cacheKey);
            // Ensure we're working with an array
            $cachedSettings = is_array($cachedSettings) ? $cachedSettings : [];
            $cachedSettings[$dbKey] = $model->options;
            Cache::put(static::$cacheKey, $cachedSettings);
        }

        // Apply config overrides immediately for this specific key
        static::applyConfigOverrides($dbKey, $model->options);

        return $model->options;
    }

    /**
     * Recursively filter out empty arrays while preserving false, null, 0, and empty strings
     */
    protected static function filterEmptyArrays(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $filtered = static::filterEmptyArrays($value);
                // Only add non-empty arrays
                if (! empty($filtered)) {
                    $result[$key] = $filtered;
                }
            } else {
                // Keep all scalar values including false, null, 0, ''
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Apply config overrides for a specific setting key
     */
    protected static function applyConfigOverrides(string $key, array $options): void
    {
        try {
            $overrideMap = static::getOverrideMap();

            if (! isset($overrideMap[$key])) {
                return;
            }

            $mappingRules = $overrideMap[$key];
            $configAlias = $mappingRules['alias'] ?? $key;

            // Apply mappings for this key/options pair
            static::applyConfigMapping($configAlias, $options, $mappingRules);
        } catch (\Throwable $e) {
            Log::error("Failed to apply config overrides: {$e->getMessage()}");
        }
    }

    /**
     * Synchronize application settings with Laravel configuration
     *
     * This method maps stored application settings to Laravel's config system,
     * applying all override rules defined in the configuration.
     */
    public static function syncConfig(): void
    {
        try {
            // Get the configuration mapping from settings to Laravel config
            $overrideMap = static::getOverrideMap();

            // Load all settings at once to reduce database calls
            $allSettings = self::getSettings();

            // Process each setting group (app, mail, etc)
            foreach ($allSettings as $settingKey => $settingValues) {
                // Skip if not a collection/array
                if (! is_iterable($settingValues)) {
                    continue;
                }

                // Get mapping rules for this setting key
                $mappingRules = $overrideMap[$settingKey] ?? [];
                $configAlias = $mappingRules['alias'] ?? $settingKey;

                // Apply mappings for this key/values pair
                static::applyConfigMapping($configAlias, $settingValues, $mappingRules);
            }
        } catch (\Throwable $e) {
            // Log the error instead of just re-throwing
            Log::error("Failed to sync application settings to config: {$e->getMessage()}");
        }
    }

    /**
     * Apply configuration mappings based on rules
     *
     * @param  string  $configAlias  Base config key
     * @param  array|iterable  $values  Values to apply
     * @param  array  $mappingRules  Rules for mapping values to config keys
     */
    protected static function applyConfigMapping(string $configAlias, $values, array $mappingRules): void
    {
        foreach ($values as $property => $value) {
            // Always set the value in its original location
            Config::set("$configAlias.$property", $value);

            // Skip if no special mapping exists
            if (! isset($mappingRules[$property])) {
                continue;
            }

            $mapping = $mappingRules[$property];

            // Handle different mapping types
            if (is_array($mapping)) {
                // Map to multiple config keys
                foreach ($mapping as $configKey) {
                    Config::set($configKey, $value);
                }
            } elseif (is_callable($mapping)) {
                // Execute custom logic
                $mapping($value);
            } else {
                // Direct mapping to a different config key
                Config::set($mapping, $value);
            }
        }
    }

    /**
     * Find settings by key and return as array
     */
    public static function find(string $key): array
    {
        return static::findByKey($key);
    }

    /**
     * Find settings by key and return as array
     */
    public static function findByKey(string $key): array
    {
        try {
            $settings = static::getSettings();

            // Simple array access
            if (is_array($settings) && isset($settings[$key])) {
                $value = $settings[$key];

                return is_array($value) ? $value : [];
            }

            return [];
        } catch (\Throwable $e) {
            Log::error("Error in findByKey: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Find settings by key and return as a Collection
     *
     * This method provides backward compatibility with code expecting a Collection.
     */
    public static function findByKeyAsCollection(string $key): Collection
    {
        return collect(static::findByKey($key));
    }

    public static function value(string $key, string $attribute, $default = null)
    {
        $options = static::findByKey($key);

        return $options[$attribute] ?? $default;
    }

    /**
     * Get all settings from cache or database
     */
    public static function getSettings(): array
    {
        $settings = Cache::remember(static::$cacheKey, 5 * 60, function () {
            return static::all()->keyBy('key')
                ->map(function ($item) {
                    return $item->options;
                })
                ->toArray();
        });

        return $settings;
    }

    /**
     * Get a specific setting value with dot notation support
     *
     * @param  mixed  $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $settingKey = array_shift($segments);
        $options = static::findByKey($settingKey);

        if (empty($options)) {
            return $default;
        }

        if (empty($segments)) {
            return empty($options) ? $default : $options;
        }

        $value = $options;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Clear settings cache
     *
     * @return void
     */
    public static function clearCache()
    {
        Cache::forget(static::$cacheKey);
    }
}
