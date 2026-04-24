<?php

namespace Foundry\Services;

use Foundry\Models\Module;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Permission;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class Helpers
{
    public static function location()
    {
        try {
            $ip = request()->ip();
            $location = request()->ipLocation();
            $agent = new Agent;
            $device = $agent->browser().' on '.$agent->platform();
            $time = now()->format('M d, Y \a\t g:i a \U\T\C');

            return collect([
                'ip' => $ip,
                'device' => $device,
                'location' => $location ? "{$location->regionName}, {$location->countryCode}" : '',
                'time' => $time,
            ]);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Load configuration settings from database and override Laravel config values
     *
     * @deprecated This method is deprecated and will be removed in a future version. Use Foundry\Model\Setting::syncConfigFromDatabase() instead.
     */
    public static function loadConfigFromDatabase(): void
    {
        try {
            // Get the configuration mapping from settings to Laravel config
            $overrideMap = config('foundry.settings_override', [
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

            // Load all settings at once to reduce database calls
            $allSettings = settings();

            // Process each setting group (app, mail, etc)
            foreach ($allSettings as $settingKey => $settingValues) {
                // Skip if not a collection/array
                if (! is_iterable($settingValues)) {
                    continue;
                }

                // Get mapping rules for this setting key
                $mappingRules = $overrideMap[$settingKey] ?? [];
                $configAlias = $mappingRules['alias'] ?? $settingKey;

                // Apply each setting value to the appropriate config
                foreach ($settingValues as $property => $value) {
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
            // Log the error instead of just re-throwing
            Log::error("Failed to load config from database: {$e->getMessage()}");
        }
    }

    /**
     * Load payment methods configuration from database
     *
     * @deprecated This method is deprecated and will be removed in a future version. Use Foundry\Models\PaymentMethod::loadPaymentMethodsConfig() instead.
     */
    public static function loadPaymentMethodsConfig(): void
    {
        try {
            PaymentMethod::syncConfig();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Check if the provided string is a valid CSS color.
     *
     * @param  string  $color
     * @return bool
     */
    public static function isValidColor($color)
    {
        // Regex to match valid hex color codes (3 or 6 characters) or named colors
        return preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color);
    }

    /**
     * Convert a directory name to its singular form if not in the excluded list.
     *
     * @param  string  $dirName  The directory name to convert.
     * @return string The singular form of the directory name or the original name if excluded.
     */
    public static function singularizeDirectoryName(string $dirName): string
    {
        // Define the array of names that should not be made singular
        $excludedDirectories = ['js', 'css', 'sass', 'scss', 'img'];

        // Check if the directory name is not in the excluded list
        if (! in_array($dirName, $excludedDirectories)) {
            return Str::singular($dirName); // Return the singular form
        }

        return $dirName; // Return the original name
    }

    public static function updateOrCreateModule($item, bool $remove = false): ?Module
    {
        if ($remove) {
            Module::where('name', $item['name'])->delete();

            return null;
        }

        $module = Module::updateOrCreate([
            'name' => $item['name'],
        ], [
            'icon' => $item['icon'],
            'url' => $item['url'],
            'show_menu' => isset($item['show_menu']) ? $item['show_menu'] : 1,
            'sort_order' => $item['sort_order'],
        ]);

        // delete removed permissions
        $module->permissions()->whereNotIn('action', $item['sub_items'])->forceDelete();

        foreach ($item['sub_items'] as $item) {
            Permission::updateOrCreate([
                'scope' => Str::slug($module['name']).':'.Str::slug($item),
            ], [
                'module_id' => $module['id'],
                'action' => $item,
            ]);
        }

        return $module;
    }
}
