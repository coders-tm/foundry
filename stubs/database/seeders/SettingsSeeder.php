<?php

namespace Database\Seeders;

use Foundry\Concerns\Helpers;
use Foundry\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    use Helpers;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $items = json_decode(replace_short_code(file_get_contents(database_path('data/settings.json'))), true);

        foreach ($items as $item) {
            Setting::updateValue($item['key'], is_array($item['options']) ? $item['options'] : json_decode($item['options'], true));
        }
    }
}
