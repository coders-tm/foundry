<?php

namespace Foundry\Database\Factories;

use Foundry\Models\WalletBalance;
use Foundry\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WalletTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $walletBalance = WalletBalance::factory()->create();
        $amount = $this->faker->randomFloat(2, 1, 500);
        $type = $this->faker->randomElement(['credit', 'debit']);

        return [
            'wallet_balance_id' => $walletBalance->id,
            'user_id' => $walletBalance->user_id,
            'type' => $type,
            'source' => $this->faker->randomElement(['refund', 'advance_payment', 'subscription_renewal']),
            'amount' => $amount,
            'balance_before' => $walletBalance->balance,
            'balance_after' => $type === 'credit'
                ? $walletBalance->balance + $amount
                : $walletBalance->balance - $amount,
            'description' => $this->faker->sentence(),
            'metadata' => [],
        ];
    }

    /**
     * Indicate that this is a credit transaction.
     */
    public function credit()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'source' => $this->faker->randomElement(['refund', 'advance_payment']),
        ]);
    }

    /**
     * Indicate that this is a debit transaction.
     */
    public function debit()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
            'source' => 'subscription_renewal',
        ]);
    }

    /**
     * Indicate that this is a refund transaction.
     */
    public function refund()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'source' => 'refund',
        ]);
    }

    /**
     * Indicate that this is a subscription renewal transaction.
     */
    public function subscriptionRenewal()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
            'source' => 'subscription_renewal',
        ]);
    }
}
