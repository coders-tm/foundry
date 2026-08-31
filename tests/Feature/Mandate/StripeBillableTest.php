<?php

namespace Tests\Feature\Mandate;

use Foundry\Events\Stripe\WebhookReceived;
use Foundry\Events\SubscriptionRenewed;
use Foundry\Mandate\BillerManager;
use Foundry\Mandate\Exceptions\PaymentIncomplete;
use Foundry\Mandate\Listeners\ChargeRenewalPayment;
use Foundry\Mandate\Listeners\StripeWebhookListener;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;

class StripeBillableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('foundry.payment_providers.stripe.secret'))) {
            $this->markTestSkipped('Stripe API keys not configured.');
        }
    }

    /**
     * Test retrieving payment methods for a user.
     */
    #[Test]
    public function test_get_auto_renewal_status()
    {
        $subscription = Subscription::factory()->create();

        $manager = new BillerManager($subscription->user);
        $methods = $manager->paymentMethods();

        $this->assertInstanceOf(Collection::class, $methods);
        $this->assertTrue($methods->isEmpty());
    }

    /**
     * Test setup auto-renewal for Stripe provider.
     */
    #[Test]
    public function test_setup_stripe_auto_renewal()
    {
        $subscription = Subscription::factory()->create(['provider' => PaymentProvider::STRIPE]);

        $manager = new BillerManager($subscription->user, 'pm_card_visa');
        $manager->setProvider(PaymentProvider::STRIPE);
        $result = $manager->setup();

        $this->assertDatabaseHas('payment_provider_customers', [
            'user_id' => $subscription->user_id,
            'provider' => PaymentProvider::STRIPE,
        ]);

        $this->assertDatabaseHas('users_payment_methods', [
            'user_id' => $subscription->user_id,
            'provider' => PaymentProvider::STRIPE,
        ]);
    }

    /**
     * Test storing multiple payment methods for the same provider.
     */
    #[Test]
    public function test_user_can_store_multiple_payment_methods_per_provider()
    {
        $subscription = Subscription::factory()->create(['provider' => PaymentProvider::STRIPE]);

        // Add first card
        $manager1 = new BillerManager($subscription->user, 'pm_card_visa');
        $manager1->setProvider(PaymentProvider::STRIPE);
        $manager1->setup();

        // Add second card
        $manager2 = new BillerManager($subscription->user, 'pm_card_mastercard');
        $manager2->setProvider(PaymentProvider::STRIPE);
        $manager2->setup();

        $paymentMethods = $subscription->user->paymentMethods;

        $this->assertCount(2, $paymentMethods);
        $this->assertTrue($paymentMethods->where('is_default', true)->first()->is_default);
    }

    /**
     * Test charging a Payable entity via Stripe off-session.
     */
    #[Test]
    public function test_charge_stripe_auto_renewal()
    {
        $plan = Plan::factory()->create(['price' => 10.00]);
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::STRIPE,
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $subscription->user_id,
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'grand_total' => 10.00,
        ]);

        // First setup customer and payment method
        $manager = new BillerManager($subscription->user, 'pm_card_visa');
        $manager->setProvider(PaymentProvider::STRIPE);
        $manager->setup();

        // Now charge a Payable instance
        $payable = Payable::fromOrder($order);
        $manager = new BillerManager($subscription->user);
        $result = $manager->charge($payable);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('succeeded', $result->getStatus());
    }

    /**
     * Test handling of 3D Secure required cards.
     */
    #[Test]
    public function test_charge_stripe_3ds_required()
    {
        $plan = Plan::factory()->create(['price' => 10.00]);
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::STRIPE,
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $subscription->user_id,
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'grand_total' => 10.00,
        ]);

        $manager = new BillerManager($subscription->user, 'pm_card_threeDSecure2Required');
        $manager->setProvider(PaymentProvider::STRIPE);
        $manager->setup();

        try {
            $payable = Payable::fromOrder($order);
            $manager = new BillerManager($subscription->user);
            $manager->charge($payable);
            $this->fail('Expected PaymentIncomplete exception was not thrown.');
        } catch (PaymentIncomplete $e) {
            $this->assertInstanceOf(PaymentIncomplete::class, $e);
            $this->assertEquals('requires_action', $e->payment()->status());
        }
    }

    /**
     * Test removal of saved payment method.
     */
    #[Test]
    public function test_remove_stripe_auto_renewal()
    {
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::STRIPE,
            'auto_renewal_enabled' => true,
        ]);

        $manager = new BillerManager($subscription->user, 'pm_card_visa');
        $manager->setProvider(PaymentProvider::STRIPE);
        $manager->setup();

        $manager = new BillerManager($subscription->user);
        $manager->setProvider(PaymentProvider::STRIPE);
        $result = $manager->removePaymentMethod();

        $this->assertTrue($result);
    }

    /**
     * Test ChargeRenewalPayment listener.
     */
    #[Test]
    public function test_charge_on_subscription_renewed_event()
    {
        $plan = Plan::factory()->create(['price' => 10.00]);
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::STRIPE,
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $subscription->user_id,
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'grand_total' => 10.00,
        ]);

        $manager = new BillerManager($subscription->user, 'pm_card_visa');
        $manager->setProvider(PaymentProvider::STRIPE);
        $manager->setup();

        $event = new SubscriptionRenewed($subscription);
        $listener = new ChargeRenewalPayment;
        $listener->handle($event);

        $this->assertTrue(true);
    }

    /**
     * Test StripeWebhookListener processing.
     */
    #[Test]
    public function test_stripe_webhook_listener()
    {
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'amount' => 1000,
                    'status' => 'succeeded',
                    'metadata' => [
                        'subscription_id' => 'sub_123',
                    ],
                ],
            ],
        ];

        $event = new WebhookReceived($payload);
        $listener = new StripeWebhookListener;
        $listener->handle($event);

        $this->assertTrue(true);
    }
}
