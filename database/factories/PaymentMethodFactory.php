<?php

namespace Foundry\Database\Factories;

use Foundry\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PaymentMethod::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => 'Stripe',
            'label' => 'Stripe',
            'provider' => PaymentMethod::STRIPE,
            'active' => true,
            'test_mode' => true,
            'credentials' => collect([
                ['key' => 'API_KEY', 'value' => 'pk_test_'.fake()->uuid(), 'publish' => true],
                ['key' => 'API_SECRET', 'value' => 'sk_test_'.fake()->uuid(), 'publish' => false],
                ['key' => 'WEBHOOK_SECRET', 'value' => 'whsec_'.fake()->uuid(), 'publish' => false],
            ]),
            'methods' => ['card'],
            'options' => [],
            'order' => 1,
        ];
    }

    /**
     * Configure the factory for Stripe payment method.
     */
    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Stripe',
            'label' => 'Stripe',
            'provider' => PaymentMethod::STRIPE,
        ]);
    }

    /**
     * Configure the factory for PayPal payment method.
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'PayPal',
            'label' => 'PayPal',
            'provider' => PaymentMethod::PAYPAL,
            'credentials' => collect([
                ['key' => 'CLIENT_ID', 'value' => fake()->uuid(), 'publish' => true],
                ['key' => 'CLIENT_SECRET', 'value' => fake()->uuid(), 'publish' => false],
            ]),
        ]);
    }

    /**
     * Configure the factory for Razorpay payment method.
     */
    public function razorpay(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Razorpay',
            'label' => 'Razorpay',
            'provider' => PaymentMethod::RAZORPAY,
            'credentials' => collect([
                ['key' => 'API_KEY', 'value' => 'rzp_test_'.fake()->uuid(), 'publish' => true],
                ['key' => 'API_SECRET', 'value' => 'rzp_test_'.fake()->uuid(), 'publish' => false],
            ]),
        ]);
    }

    /**
     * Configure the factory for manual payment method.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Manual',
            'label' => 'Manual Payment',
            'provider' => PaymentMethod::MANUAL,
            'credentials' => collect([]),
            'methods' => [],
        ]);
    }

    /**
     * Configure the factory for Flutterwave payment method.
     */
    public function flutterwave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Flutterwave',
            'label' => 'Flutterwave',
            'provider' => PaymentMethod::FLUTTERWAVE,
            'credentials' => collect([
                ['key' => 'CLIENT_ID', 'value' => fake()->uuid(), 'publish' => true],
                ['key' => 'CLIENT_SECRET', 'value' => fake()->uuid(), 'publish' => false],
                ['key' => 'ENCRYPTION_KEY', 'value' => base64_encode(fake()->sha256()), 'publish' => false],
            ]),
            'methods' => ['card', 'banktransfer', 'ussd', 'account'],
        ]);
    }

    /**
     * Configure the factory for GoCardless payment method.
     */
    public function gocardless(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'GoCardless',
            'label' => 'Direct Debit (GoCardless)',
            'provider' => PaymentMethod::GOCARDLESS,
            'credentials' => collect([
                ['key' => 'ACCESS_TOKEN', 'value' => 'sandbox_'.fake()->sha256(), 'publish' => false],
                ['key' => 'WEBHOOK_SECRET', 'value' => fake()->sha256(), 'publish' => false],
            ]),
            'methods' => ['direct_debit', 'bacs', 'sepa_core', 'ach', 'becs'],
        ]);
    }

    /**
     * Configure the factory for Klarna payment method.
     */
    public function klarna(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Klarna',
            'label' => 'Klarna',
            'provider' => PaymentMethod::KLARNA,
            'credentials' => collect([
                ['key' => 'API_KEY', 'value' => fake()->uuid(), 'publish' => false],
                ['key' => 'API_SECRET', 'value' => 'klarna_test_api_'.base64_encode(fake()->sha256()), 'publish' => false],
            ]),
            'methods' => ['pay_later', 'pay_in_3', 'pay_now', 'slice_it'],
        ]);
    }

    /**
     * Configure the factory for active payment method.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Configure the factory for inactive payment method.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
