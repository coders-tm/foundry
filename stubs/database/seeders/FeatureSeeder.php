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
                'label' => 'Users',
                'slug' => 'users',
                'resetable' => false,
                'description' => 'Maximum number of users allowed.',
            ],
            [
                'label' => 'Projects',
                'slug' => 'projects',
                'resetable' => false,
                'description' => 'Maximum number of projects allowed.',
            ],
            [
                'label' => 'Storage',
                'slug' => 'storage',
                'resetable' => false,
                'description' => 'Storage limit in MB.',
            ],
            [
                'label' => 'Support',
                'slug' => 'support',
                'type' => 'boolean',
                'resetable' => false,
                'description' => 'Access to customer support.',
            ],
            [
                'label' => 'API Access',
                'slug' => 'api',
                'type' => 'boolean',
                'resetable' => false,
                'description' => 'Access to the developer API.',
            ],
            [
                'label' => 'SSO',
                'slug' => 'sso',
                'type' => 'boolean',
                'resetable' => false,
                'description' => 'Single Sign-On capability.',
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
