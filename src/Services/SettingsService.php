<?php

/*
 * Created At: 2026-05-06
 * Author: Antigravity
 */

namespace Foundry\Services;

use Foundry\Events\SettingChanged;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SettingsService
{
    /**
     * The path to the settings JSON file.
     */
    protected string $path;

    /**
     * Create a new settings service instance.
     */
    public function __construct()
    {
        $this->path = config('foundry.settings_path', resource_path('settings.json'));
    }

    /**
     * Set the path to the settings file (useful for tests).
     *
     * @return $this
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Load settings from JSON into Laravel configuration.
     */
    public function load(): void
    {
        try {
            if (! file_exists($this->path)) {
                Config::set('settings', []);

                return;
            }

            $content = file_get_contents($this->path);
            $settings = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse settings.json: '.json_last_error_msg());
                Config::set('settings', []);

                return;
            }

            Config::set('settings', $settings ?? []);

            $this->syncConfig();
        } catch (\Throwable $e) {
            Log::error("Failed to load settings: {$e->getMessage()}");
        }
    }

    /**
     * Get a setting value using dot notation.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Config::get("settings.{$key}", $default);
    }

    /**
     * Set a setting value and update the JSON storage.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     */
    public function set($key, $value = null): void
    {
        try {
            $settings = [];
            if (file_exists($this->path)) {
                $content = file_get_contents($this->path);
                $settings = json_decode($content, true) ?? [];
            }

            $changes = [];
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    if (Arr::get($settings, $k) !== $v) {
                        Arr::set($settings, $k, $v);
                        $changes[$k] = $v;
                    }
                }
            } else {
                if (Arr::get($settings, $key) !== $value) {
                    Arr::set($settings, $key, $value);
                    $changes[$key] = $value;
                }
            }

            if (empty($changes)) {
                return;
            }

            // Ensure the directory exists
            $directory = dirname($this->path);
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($this->path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Reload into config
            Config::set('settings', $settings);
            $this->syncConfig();

            // Fire events for each changed key
            foreach ($changes as $k => $v) {
                event(new SettingChanged($k, $v));
            }
        } catch (\Throwable $e) {
            Log::error("Failed to save setting: {$e->getMessage()}");
        }
    }

    /**
     * Sync settings with Laravel config based on the override map.
     */
    public function syncConfig(): void
    {
        try {
            $overrideMap = config('foundry.settings_override', []);
            $allSettings = Config::get('settings', []);

            // Process each setting group (config, mail, etc)
            foreach ($allSettings as $settingKey => $settingValues) {
                // Skip if not a collection/array
                if (! is_iterable($settingValues)) {
                    continue;
                }

                // Get mapping rules for this setting key
                $mappingRules = $overrideMap[$settingKey] ?? [];
                $configAlias = $mappingRules['alias'] ?? $settingKey;

                // Flatten the settings values to dot notation
                $flatSettings = $this->flatten($settingValues);

                // Apply each setting value to the appropriate config
                foreach ($flatSettings as $property => $value) {
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
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     */
    protected function flatten(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value) && ! array_is_list($value)) {
                $results = array_merge($results, $this->flatten($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Get the default override map with all handlers.
     */
    protected function getOverrideMap(): array
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
     * Get all settings.
     */
    public function all(): array
    {
        return Config::get('settings', []);
    }
}
