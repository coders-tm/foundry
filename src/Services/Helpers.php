<?php

namespace Foundry\Services;

use Foundry\Models\Module;
use Foundry\Models\Permission;
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
