<?php

namespace Foundry\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class SupportTicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model|TModel>
     */
    protected $model = 'Foundry\\Models\\SupportTicket';

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->email(),
            'phone' => fake()->phoneNumber(),
            'subject' => fake()->sentence(),
            'message' => fake()->paragraph(),
            'status' => ['pending', 'replied', 'staff_replied', 'resolved'][rand(0, 3)],
        ];
    }
}
