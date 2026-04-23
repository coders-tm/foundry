<?php

namespace Foundry\Database\Factories;

use Foundry\Models\User;
use Foundry\Models\WalletBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletBalanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WalletBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'balance' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }

    /**
     * Indicate that the wallet has zero balance.
     */
    public function empty()
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0.00,
        ]);
    }

    /**
     * Indicate that the wallet has a specific balance.
     */
    public function withBalance(float $balance)
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }
}
