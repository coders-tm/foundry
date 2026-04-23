<?php

namespace Foundry\Services;

use Foundry\Foundry;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;

/**
 * Safe Fluent class that can be safely rendered in templates
 * Extends Fluent but provides safe string conversion
 */
class SafeFluent extends Fluent
{
    /**
     * Convert the object to its string representation safely
     */
    public function __toString(): string
    {
        // Return a safe default when object is converted to string
        return '';
    }
}

class ShortcodeProcessor
{
    /**
     * Process data into shortcode replacements
     * Supports legacy UPPERCASE format {{USER_FIRST_NAME}} for backward compatibility
     * Use Blade syntax {{ $user->first_name }} for modern templates
     *
     * @param  array  $data  Raw data to process
     * @return array Shortcode replacements map
     */
    public function process(array $data = []): array
    {
        // Build default global data
        $defaultData = $this->getDefaultData();

        // Merge custom app shortcodes into defaultData (as structured data, not shortcodes)
        if (isset(Foundry::$appShortCodes) && is_array(Foundry::$appShortCodes)) {
            $defaultData = $this->mergeRecursive($defaultData, Foundry::$appShortCodes);
        }

        // Merge user data over defaults (user data takes precedence, but preserve nested defaults)
        $data = $this->mergeRecursive($defaultData, $data);

        return $this->buildReplacements($data);
    }

    /**
     * Recursively merge arrays, preserving nested structure
     * Unlike array_merge_recursive, this overwrites scalar values instead of creating arrays
     */
    protected function mergeRecursive(array $defaults, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                // Both are arrays - merge recursively
                $defaults[$key] = $this->mergeRecursive($defaults[$key], $value);
            } else {
                // Override with new value (scalar, object, or replacing array entirely)
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    /**
     * Get default global shortcode data
     */
    protected function getDefaultData(): array
    {
        return [
            'app' => [
                'domain' => config('foundry.domain'),
                'email' => config('foundry.admin_email'),
                'name' => config('app.name'),
                'url' => config('app.url'),
            ],
            'support' => [
                'email' => config('foundry.admin_email'),
            ],
            'pages' => [
                'billing' => app_url('billing'),
                'member' => config('app.url'),
                'admin' => config('foundry.admin_url'),
            ],
        ];
    }

    /**
     * Build shortcode replacements from data
     * Converts arrays/objects to UPPERCASE format only
     * Process scalars AFTER arrays so scalar values override nested ones
     */
    protected function buildReplacements(array $data): array
    {
        $replacements = [];
        $scalarQueue = []; // Process scalars last so they override

        foreach ($data as $key => $value) {
            if (is_object($value) && method_exists($value, 'toArray')) {
                // Convert model to shortcodes
                $this->processModelShortcodes($replacements, $key, $value);
            } elseif (is_array($value)) {
                // Process nested array data
                $this->processArrayShortcodes($replacements, $key, $value);

                // Special handling for 'pages' key to support {{MEMBER_PAGE}}, {{ADMIN_PAGE}}, etc.
                if ($key === 'pages') {
                    $this->processPageShortcodes($replacements, $value);
                }
            } elseif (is_scalar($value) || is_null($value)) {
                // Queue scalar values for processing last (so they override nested values)
                $scalarQueue[$key] = $value;
            }
        }

        // Process scalars last so they take precedence over nested values
        foreach ($scalarQueue as $key => $value) {
            $this->processScalarShortcode($replacements, $key, $value);
        }

        return $replacements;
    }

    /**
     * Process model object into shortcodes (UPPERCASE format only)
     */
    protected function processModelShortcodes(array &$replacements, string $key, object $model): void
    {
        $prefixUpper = strtoupper($key);

        foreach ($model->toArray() as $attr => $val) {
            if (is_scalar($val) || is_null($val)) {
                // Legacy UPPERCASE format: {{USER_FIRST_NAME}}
                $replacements['{{'.$prefixUpper.'_'.strtoupper($attr).'}}'] = $val ?? '';
            }
        }

        // Also add the key itself for backward compatibility
        // {{USER}} (if value can be converted to string)
        if (method_exists($model, '__toString')) {
            $replacements['{{'.$prefixUpper.'}}'] = (string) $model;
        }
    }

    /**
     * Process array into shortcodes (UPPERCASE format only)
     */
    protected function processArrayShortcodes(array &$replacements, string $key, array $data): void
    {
        $prefixUpper = strtoupper($key);

        foreach ($data as $subKey => $subVal) {
            if (is_scalar($subVal) || is_null($subVal)) {
                // Legacy UPPERCASE format: {{USER_FIRST_NAME}}
                $replacements['{{'.$prefixUpper.'_'.strtoupper($subKey).'}}'] = $subVal ?? '';
            }
        }
    }

    /**
     * Process page shortcuts for backward compatibility
     * Generates {{BILLING_PAGE}}, {{MEMBER_PAGE}}, {{ADMIN_PAGE}} from pages array
     */
    protected function processPageShortcodes(array &$replacements, array $pages): void
    {
        foreach ($pages as $pageKey => $pageValue) {
            if (is_scalar($pageValue) || is_null($pageValue)) {
                // Special shortcut format: {{BILLING_PAGE}} instead of {{PAGES_BILLING}}
                $replacements['{{'.strtoupper($pageKey).'_PAGE}}'] = $pageValue ?? '';
            }
        }
    }

    /**
     * Process scalar value into shortcode (UPPERCASE format only)
     *
     * @param  mixed  $value
     */
    protected function processScalarShortcode(array &$replacements, string $key, $value): void
    {
        // Legacy UPPERCASE format: {{APP_NAME}}
        $replacements['{{'.strtoupper($key).'}}'] = $value ?? '';
    }

    /**
     * Convert arrays to object form (Fluent) recursively for Blade access
     * - Merges default data with user data
     * - Associative arrays become Fluent objects (property + ArrayAccess)
     *
     * @param  array  $data  User data to convert
     * @return array Array where each value is converted to Fluent object
     */
    public function toObject(array $data = []): array
    {
        // Build default global data
        $defaultData = $this->getDefaultData();

        // Merge custom app shortcodes
        if (isset(Foundry::$appShortCodes) && is_array(Foundry::$appShortCodes)) {
            $defaultData = $this->mergeRecursive($defaultData, Foundry::$appShortCodes);
        }

        // Merge user data over defaults (user data takes precedence, but preserve nested defaults)
        $data = $this->mergeRecursive($defaultData, $data);

        $converted = [];

        foreach ($data as $k => $v) {
            $converted[$k] = $this->convertValue($v);
        }

        return $converted;
    }

    /**
     * Convert a single value to appropriate type for Blade access
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function convertValue($value)
    {
        // Convert objects with toArray() method to arrays first
        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            // Determine if associative
            $isAssoc = Arr::isAssoc($value);

            if ($isAssoc) {
                // Convert associative array to safe Fluent object with __toString method
                $converted = [];
                foreach ($value as $k => $v) {
                    $converted[$k] = $this->convertValue($v);
                }

                return new SafeFluent($converted);
            }

            // Indexed array - map recursively
            return array_map(fn ($v) => $this->convertValue($v), $value);
        }

        // Leave other objects/scalars unchanged
        return $value;
    }
}
