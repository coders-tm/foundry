<?php

namespace Database\Seeders;

use Foundry\Models\Subscription\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $rows = [
            [
                'label' => 'Locations',
                'slug' => 'locations',
                'resetable' => true, // false; credit will not reset on subscription renewal
                'description' => 'Maximum locations can be created.',
            ],
            [
                'label' => 'Staff',
                'slug' => 'staff',
                'resetable' => true,
                'description' => 'Maximum staff can be created.',
            ],
            [
                'label' => 'Support',
                'slug' => 'support',
                'type' => 'boolean',
                'resetable' => false,
                'description' => '24x7 Dedicated Support.',
            ],
        ];

        Feature::whereNotIn('slug', collect($rows)->map(function ($item) {
            return $item['slug'];
        }))->delete();

        foreach ($rows as $item) {
            Feature::updateOrCreate(['label' => $item['label']], $item);
        }
    }
}
