<?php

namespace Foundry\Database\Factories\Order;

use Foundry\Models\Order\DiscountLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountLineFactory extends Factory
{
    protected $model = DiscountLine::class;

    public function definition()
    {
        return [
            'type' => $this->faker->randomElement([DiscountLine::TYPE_PERCENTAGE, DiscountLine::TYPE_FIXED_AMOUNT]),
            'value' => $this->faker->randomFloat(2, 5, 50),
            'description' => $this->faker->sentence,
            'coupon_id' => $this->faker->uuid,
            'coupon_code' => $this->faker->word,
            'discountable_type' => 'Foundry\Models\Order',
            'discountable_id' => $this->faker->uuid,
        ];
    }
}
