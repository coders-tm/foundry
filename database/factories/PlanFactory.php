<?php

namespace Foundry\Database\Factories;

use Foundry\Models\Subscription\Feature;
use Foundry\Models\Subscription\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition()
    {
        $interval = $this->faker->randomElement(['week', 'month', 'year']);

        return [
            'label' => $this->faker->word,
            'description' => $this->faker->sentence,
            'is_active' => $this->faker->boolean,
            'default_interval' => $interval,
            'interval' => $interval,
            'interval_count' => $this->faker->numberBetween(1, 12),
            'is_contract' => false, // Default to non-contract
            'contract_cycles' => null, // Null = unlimited for non-contract plans
            'allow_freeze' => true, // Default to allowing freeze
            'freeze_fee' => null, // Null = use global config
            'grace_period_days' => 0, // Default: no grace period (expires immediately)
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'trial_days' => $this->faker->numberBetween(0, 30),
        ];
    }

    /**
     * Indicate that the plan is a contract plan with a specific number of cycles.
     */
    public function contract(int $contractCycles = 12): static
    {
        return $this->state(fn (array $attributes) => [
            'is_contract' => true,
            'contract_cycles' => $contractCycles,
        ]);
    }

    /**
     * Indicate that the plan allows freezing with custom fee.
     */
    public function allowingFreeze(?float $fee = null): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_freeze' => true,
            'freeze_fee' => $fee,
        ]);
    }

    /**
     * Indicate that the plan does not allow freezing.
     */
    public function disallowingFreeze(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_freeze' => false,
            'freeze_fee' => null,
        ]);
    }

    /**
     * Set custom grace period days.
     */
    public function withGracePeriod(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'grace_period_days' => $days,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($plan) {
            $features = [];

            foreach (Feature::all() as $key => $item) {
                $features[$item->slug] = $item->isBoolean() ? rand(0, 1) : rand(0, 20);
            }

            $plan->syncFeatures($features);
        });
    }
}
