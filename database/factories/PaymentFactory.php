<?php

namespace Foundry\Database\Factories;

use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'payment_method_id' => PaymentMethod::factory(),
            'transaction_id' => 'txn_'.$this->faker->unique()->regexify('[A-Z0-9]{24}'),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'status' => Payment::STATUS_COMPLETED,
            'currency' => 'USD',
            'fees' => $this->faker->randomFloat(2, 0, 50),
            'net_amount' => function (array $attributes) {
                return $attributes['amount'] - ($attributes['fees'] ?? 0);
            },
            'processed_at' => now(),
            'metadata' => [],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_PENDING,
            'processed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_FAILED,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Payment::STATUS_REFUNDED,
            'refund_amount' => $attributes['amount'],
        ]);
    }

    public function withStripeMetadata(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'payment_method_details' => [
                    'type' => 'card',
                    'card' => [
                        'brand' => 'visa',
                        'last4' => '4242',
                        'exp_month' => 12,
                        'exp_year' => 2025,
                        'funding' => 'credit',
                    ],
                ],
            ],
        ]);
    }
}
