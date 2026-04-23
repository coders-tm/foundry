<?php

namespace Foundry\Database\Factories;

use Foundry\Models\Subscription\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    public function definition()
    {
        return [
            'label' => $this->faker->word,
            'slug' => $this->faker->unique()->slug,
            'type' => $this->faker->randomElement(['integer', 'boolean']),
            'resetable' => $this->faker->boolean,
            'description' => $this->faker->sentence,
        ];
    }

    public function countable(): static
    {
        return $this->state([
            'type' => 'integer',
        ]);
    }
}
