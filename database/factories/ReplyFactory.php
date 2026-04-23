<?php

namespace Foundry\Database\Factories;

use Foundry\Foundry;
use Foundry\Models\SupportTicket\Reply;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class ReplyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model|TModel|Reply>
     */
    protected $model = Reply::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'message' => fake()->paragraph(),
            'user_id' => Foundry::$adminModel::inRandomOrder()->first()->id,
        ];
    }
}
