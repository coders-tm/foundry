<?php

namespace Foundry\Database\Factories;

use Foundry\Models\Address;
use Foundry\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model|TModel>
     */
    protected $model = 'Foundry\\Models\\User';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $created_at = fake()->dateTimeBetween('-3 years');
        $gender = ['male', 'female'][rand(0, 1)];

        return [
            'first_name' => fake()->firstName($gender),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail,
            'gender' => $gender,
            'phone_number' => fake()->e164PhoneNumber(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            'status' => fake()->randomElement(['active', 'pending']),
            'created_at' => $created_at,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    /**
     * Indicate that the model's status should be deactive.
     *
     * @return static
     */
    public function deactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            $user->updateOrCreateAddress(Address::factory()->make()->toArray());
        });
    }
}
