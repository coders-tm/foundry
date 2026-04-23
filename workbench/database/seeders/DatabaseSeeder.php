<?php

namespace Workbench\Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            \Database\Seeders\DatabaseSeeder::class,
            // OrderReportsSeeder::class,
        ]);

        // create a normal user
        User::updateOrCreate([
            'email' => 'hello@foundry.com',
        ], User::factory()->make()->only([
            'first_name',
            'last_name',
            'gender',
            'phone_number',
            'email_verified_at',
            'password',
            'remember_token',
            'status',
        ]));

        // Create a admin user
        Admin::updateOrCreate([
            'email' => 'hello@foundry.com',
        ], Admin::factory()->admin()->make()->only([
            'first_name',
            'last_name',
            'gender',
            'phone_number',
            'email_verified_at',
            'password',
            'remember_token',
        ]) + [
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        Artisan::call('foundry:update-exchange-rates');
    }
}
