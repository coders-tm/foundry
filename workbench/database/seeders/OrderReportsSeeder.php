<?php

namespace Workbench\Database\Seeders;

use App\Models\User;
use Faker\Factory;
use Foundry\Models\Coupon;
use Foundry\Models\Order;
use Foundry\Models\Refund;
use Foundry\Models\Subscription\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderReportsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Factory::create();

        // Create some users if they don't exist
        $users = User::count() > 0 ? User::limit(10)->get() : User::factory()->count(10)->create();

        // Create some plans if they don't exist
        $plans = Plan::count() > 0 ? Plan::limit(20)->get() : $this->createPlans();

        // Create discount coupons
        $coupons = $this->createCoupons();

        // Countries for geographic distribution
        $countries = ['United States', 'Canada', 'United Kingdom', 'Germany', 'France', 'Australia', 'Japan', 'Brazil'];

        // Shipping carriers
        $carriers = ['UPS', 'FedEx', 'USPS', 'DHL', 'Canada Post', 'Royal Mail'];

        // Order sources
        $sources = ['website', 'mobile_app', 'pos', 'marketplace', 'social_media'];

        $this->command->info('Creating orders with faker data...');

        // Create 200 orders with varied scenarios
        for ($i = 0; $i < 200; $i++) {
            $user = $users->random();
            $createdAt = $faker->dateTimeBetween('-90 days', 'now');

            // Determine order status (90% paid, 10% pending)
            $paymentStatus = $faker->boolean(90) ? 'paid' : 'pending';

            $status = $paymentStatus === 'paid' ? 'completed' : 'pending';

            // Create shipping address
            $country = $faker->randomElement($countries);
            $shippingAddress = [
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'address' => $faker->streetAddress,
                'city' => $faker->city,
                'state' => $faker->state,
                'postcode' => $faker->postcode,
                'country' => $country,
            ];

            $order = Order::create([
                'customer_id' => $user->id,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'source' => $faker->randomElement($sources),
                'sub_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'grand_total' => 0,
                'billing_address' => $shippingAddress,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Add line items (1-3 plans per order)
            $itemCount = $faker->numberBetween(1, 3);
            $subTotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $plan = $plans->random();

                $quantity = 1;
                $price = $plan->price;
                $total = $price * $quantity;
                $subTotal += $total;

                $order->line_items()->create([
                    'itemable_id' => $plan->id,
                    'itemable_type' => Plan::class,
                    'title' => $plan->label,
                    'price' => $price,
                    'quantity' => $quantity,
                ]);
            }

            // Add tax (8%)
            $taxTotal = $subTotal * 0.08;

            // Maybe add discount (30% chance)
            $discountTotal = 0;
            if ($faker->boolean(30) && $coupons->count() > 0) {
                $coupon = $coupons->random();

                // Create discount line for this order
                $discountLine = $order->discount()->create([
                    'description' => $coupon->name,
                    'coupon_code' => $coupon->promotion_code,
                    'coupon_id' => $coupon->id,
                    'value' => $coupon->value,
                    'type' => $coupon->discount_type === 'percentage' ? 'percentage' : 'fixed_amount',
                ]);

                // Calculate discount
                if ($coupon->discount_type === 'percentage') {
                    $discountTotal = $subTotal * ($coupon->value / 100);
                } else {
                    $discountTotal = min($coupon->value, $subTotal);
                }
            }

            $grandTotal = $subTotal + $taxTotal - $discountTotal;

            // Update order totals
            $order->update([
                'sub_total' => $subTotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'paid_total' => $paymentStatus === 'paid' ? $grandTotal : 0,
            ]);

            // Maybe add refund (5% chance for paid orders)
            if ($paymentStatus === 'paid' && $faker->boolean(5)) {
                $refundAmount = $faker->randomFloat(2, $grandTotal * 0.1, $grandTotal);

                Refund::create([
                    'order_id' => $order->id,
                    'amount' => $refundAmount,
                    'reason' => $faker->randomElement([
                        'Customer request',
                        'Plan cancellation',
                        'Billing error',
                    ]),
                ]);

                $order->update([
                    'refund_total' => $refundAmount,
                ]);
            }

            // Progress indicator
            if (($i + 1) % 50 === 0) {
                $this->command->info('Created '.($i + 1).' orders...');
            }
        }

        $this->command->info('Order reports seeder completed successfully!');
        $this->command->info('Created 200 orders with realistic subscription data for dashboard testing.');
    }

    /**
     * Create sample plans
     */
    protected function createPlans()
    {
        $faker = Factory::create();
        $plans = collect();

        $planNames = [
            'Basic Monthly Plan',
            'Pro Monthly Plan',
            'Enterprise Monthly Plan',
            'Basic Annual Plan',
            'Pro Annual Plan',
            'Enterprise Annual Plan',
        ];

        foreach ($planNames as $name) {
            $plan = Plan::create([
                'label' => $name,
                'description' => $faker->paragraph,
                'is_active' => true,
                'price' => $faker->randomFloat(2, 29, 299),
                'trial_days' => 7,
                'interval' => Str::contains($name, 'Annual') ? 'year' : 'month',
                'interval_count' => 1,
            ]);

            $plans->push($plan);
        }

        return $plans;
    }

    /**
     * Create sample coupons
     */
    protected function createCoupons()
    {
        $coupons = collect();

        $couponData = [
            ['code' => 'SAVE10', 'type' => 'plan', 'discount_type' => 'percentage', 'value' => 10],
            ['code' => 'SAVE20', 'type' => 'plan', 'discount_type' => 'percentage', 'value' => 20],
            ['code' => 'WELCOME15', 'type' => 'plan', 'discount_type' => 'percentage', 'value' => 15],
            ['code' => 'DEAL50', 'type' => 'plan', 'discount_type' => 'fixed', 'value' => 50],
        ];

        foreach ($couponData as $data) {
            $coupon = Coupon::firstOrCreate(
                ['promotion_code' => $data['code']],
                [
                    'name' => $data['code'],
                    'promotion_code' => $data['code'],
                    'type' => $data['type'],
                    'discount_type' => $data['discount_type'],
                    'value' => $data['value'],
                    'duration' => 'once',
                    'auto_apply' => false,
                    'active' => true,
                ]
            );
            $coupons->push($coupon);
        }

        return $coupons;
    }
}
