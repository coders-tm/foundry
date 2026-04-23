<?php

namespace Foundry\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\ClassSchedule;

class ClassScheduleFactory extends Factory
{
    protected $model = ClassSchedule::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'date_at' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'start_at' => '09:00',
            'end_at' => '10:00',
            'sign_off_at' => null,
            'is_active' => true,
        ];
    }
}
