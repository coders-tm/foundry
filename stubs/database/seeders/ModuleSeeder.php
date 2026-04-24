<?php

namespace Database\Seeders;

use Foundry\Concerns\Helpers;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    use Helpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = json_decode(file_get_contents(database_path('data/modules.json')), true);

        foreach ($modules as $name => $module) {
            $this->updateOrCreateModule(array_merge($module, ['name' => $name]));
        }
    }
}
