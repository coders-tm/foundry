<?php

namespace Foundry\Database\Factories;

use Foundry\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['order_invoice', 'user_welcome', 'order_shipped']),
            'subject' => $this->faker->sentence,
            'content' => $this->faker->paragraph,
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the notification is the default for its type.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Create a notification with safe Blade template
     */
    public function withSafeTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => 'Hello @if(true) {{ $name }} @endif',
        ]);
    }

    /**
     * Create a notification with dangerous template (for testing security)
     */
    public function withDangerousTemplate(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => '@php echo "hacked"; @endphp',
        ]);
    }
}
