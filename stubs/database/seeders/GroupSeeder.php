<?php

namespace Database\Seeders;

use Foundry\Models\Group;
use Foundry\Models\Permission;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $group = Group::firstOrCreate([
            'name' => 'Admin',
        ], [
            'description' => 'Full access to the system',
        ]);

        $sales = Group::firstOrCreate([
            'name' => 'Sales',
        ], [
            'description' => 'Limited access to the system',
        ]);

        $group->syncPermissions(Permission::all()->map(function ($permission) {
            return [
                'scope' => $permission->scope,
                'access' => true,
            ];
        }));

        $sales->syncPermissions(Permission::where('scope', 'like', '%read%')->get()
            ->map(function ($permission) {
                return [
                    'scope' => $permission->scope,
                    'access' => true,
                ];
            }));
    }
}
