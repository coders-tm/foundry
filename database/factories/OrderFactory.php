<?php

namespace Foundry\Database\Factories;

use Foundry\Models\Order;
use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Subscription;
use Foundry\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'collect_tax' => rand(0, 1),
            'paid_total' => 0.00,
            'refund_total' => 0.00,
            'line_items_quantity' => 0,
            'number' => $this->faker->unique()->numerify('##########'),
            'created_at' => fake()->dateTimeBetween('-3 years'),
        ];
    }

    /**
     * Create an order with complete details including line items, discounts, and taxes.
     *
     * Usage:
     * - Order::factory()->complete()->create()
     * - Order::factory()->complete(overrides: ['customer_id' => $user->id])->create()
     */
    public function complete(
        array $lineItems = [],
        array $discount = [],
        array $taxLines = [],
        array $overrides = []
    ) {
        return $this->afterCreating(function (Order $order) use ($lineItems, $discount, $taxLines) {
            // Use provided line items or create default ones
            $items = ! empty($lineItems) ? $lineItems : [
                [
                    'title' => 'Premium Plan',
                    'sku' => 'PREMIUM-001',
                    'price' => 99.99,
                    'quantity' => 1,
                    'taxable' => true,
                ],
                [
                    'title' => 'Setup Fee',
                    'sku' => 'SETUP-001',
                    'price' => 29.99,
                    'quantity' => 1,
                    'taxable' => true,
                ],
            ];

            // Calculate subtotal from line items
            $subTotal = collect($items)->sum(fn ($item) => $item['price'] * $item['quantity']);

            // Apply discount if provided or use default
            $discountData = ! empty($discount) ? $discount : [
                'type' => 'percentage',
                'value' => 10,
                'description' => 'Early Bird Discount',
            ];

            $discountAmount = $discountData['type'] === 'percentage'
                ? round($subTotal * ($discountData['value'] / 100), 2)
                : round($discountData['value'], 2);

            $discountedSubtotal = $subTotal - $discountAmount;

            // Apply tax if provided or use default
            $taxData = ! empty($taxLines) ? $taxLines : [
                [
                    'label' => 'Sales Tax',
                    'rate' => 8.5,
                    'type' => 'normal',
                ],
            ];

            // Calculate total tax amount
            $taxAmount = 0;
            foreach ($taxData as $tax) {
                $taxAmount += round($discountedSubtotal * ($tax['rate'] / 100), 2);
            }

            $grandTotal = round($discountedSubtotal + $taxAmount, 2);

            // Update order totals
            $order->update([
                'sub_total' => $subTotal,
                'discount_total' => $discountAmount,
                'tax_total' => $taxAmount,
                'grand_total' => $grandTotal,
                'line_items_quantity' => count($items),
                'collect_tax' => true,
            ]);

            // Sync line items
            $order->syncLineItems(collect($items));

            // Create discount
            $order->discount()->save(new DiscountLine($discountData));

            // Create tax lines with calculated amounts
            $taxAmountPerLine = $taxAmount / count($taxData);
            foreach ($taxData as $tax) {
                $order->tax_lines()->create([
                    'label' => $tax['label'],
                    'rate' => $tax['rate'],
                    'amount' => round($taxAmountPerLine, 2),
                    'type' => $tax['type'] ?? 'normal',
                ]);
            }
        });
    }

    /**
     * Create an order with a subscription relationship
     */
    public function forSubscription(Subscription|int|null $subscription = null)
    {
        return $this->state(function (array $attributes) use ($subscription) {
            if ($subscription instanceof Subscription) {
                $subscriptionId = $subscription->id;
                $userId = $subscription->user_id;
            } elseif (is_int($subscription)) {
                $subscriptionId = $subscription;
                $sub = Subscription::find($subscription);
                $userId = $sub?->user_id;
            } else {
                $sub = Subscription::factory()->create();
                $subscriptionId = $sub->id;
                $userId = $sub->user_id;
            }

            return array_merge($attributes, [
                'customer_id' => $userId,
                'orderable_id' => $subscriptionId,
                'orderable_type' => Subscription::class,
            ]);
        });
    }

    /**
     * Create an order with a user (customer)
     */
    public function forUser(User|int|null $user = null)
    {
        return $this->state(function (array $attributes) use ($user) {
            if ($user instanceof User) {
                $userId = $user->id;
            } elseif (is_int($user)) {
                $userId = $user;
            } else {
                $userId = User::factory()->create()->id;
            }

            return array_merge($attributes, [
                'customer_id' => $userId,
            ]);
        });
    }

    /**
     * Create an order with tax collection enabled
     */
    public function withTax()
    {
        return $this->state([
            'collect_tax' => true,
        ]);
    }

    /**
     * Create an order with tax collection disabled
     */
    public function withoutTax()
    {
        return $this->state([
            'collect_tax' => false,
        ]);
    }

    /**
     * Create a paid order
     */
    public function paid(?float $amount = null)
    {
        return $this->afterCreating(function (Order $order) use ($amount) {
            $order->update([
                'paid_total' => $amount ?? $order->grand_total,
                'payment_status' => Order::STATUS_PAID,
            ]);
        });
    }

    /**
     * Create a cancelled order
     */
    public function cancelled(?string $reason = null)
    {
        return $this->state([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Create a completed order
     */
    public function completed()
    {
        return $this->state([
            'status' => Order::STATUS_COMPLETED,
        ]);
    }
}
