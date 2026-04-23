<?php

namespace Foundry\Database\Factories;

use Foundry\Models\Subscription;
use Foundry\Models\Subscription\SubscriptionFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFeatureFactory extends Factory
{
    protected $model = SubscriptionFeature::class;

    public function definition()
    {
        return [
            'subscription_id' => Subscription::factory(),
            'slug' => $this->faker->unique()->slug,
            'label' => $this->faker->word,
            'type' => $this->faker->randomElement(['integer', 'boolean']),
            'resetable' => $this->faker->boolean,
            'value' => $this->faker->numberBetween(0, 100),
            'used' => $this->faker->numberBetween(0, 50),
        ];
    }
}
