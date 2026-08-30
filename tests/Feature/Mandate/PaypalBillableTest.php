<?php

namespace Tests\Feature\Mandate;

use Foundry\Exceptions\PaymentException;
use Foundry\Foundry;
use Foundry\Mandate\BillerManager;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Srmklive\PayPal\Services\PayPal;

class PaypalBillableTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setStaticPaypalClient(null);
        parent::tearDown();
    }

    protected function mockPaypal()
    {
        $mock = $this->mock(PayPal::class);
        $mock->shouldReceive('setApiCredentials')->andReturnSelf();
        $mock->shouldReceive('getAccessToken')->andReturn(['access_token' => 'mock-token']);

        $this->setStaticPaypalClient($mock);

        return $mock;
    }

    protected function setStaticPaypalClient($client)
    {
        $property = new ReflectionProperty(Foundry::class, 'paypalClient');
        $property->setAccessible(true);
        $property->setValue(null, $client);
    }

    /**
     * Test setup auto-renewal for PayPal provider.
     */
    #[Test]
    public function test_setup_paypal_auto_renewal()
    {
        $subscription = Subscription::factory()->create(['provider' => PaymentProvider::PAYPAL]);

        $manager = new BillerManager($subscription->user, 'VAULT-ID-123');
        $manager->setProvider(PaymentProvider::PAYPAL);
        $result = $manager->setup();

        $this->assertDatabaseHas('payment_provider_customers', [
            'user_id' => $subscription->user_id,
            'provider' => PaymentProvider::PAYPAL,
        ]);

        $this->assertDatabaseHas('users_payment_methods', [
            'user_id' => $subscription->user_id,
            'provider' => PaymentProvider::PAYPAL,
            'provider_id' => 'VAULT-ID-123',
        ]);
    }

    /**
     * Test auto-renewal removal for PayPal.
     */
    #[Test]
    public function test_remove_paypal_auto_renewal()
    {
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::PAYPAL,
            'auto_renewal_enabled' => true,
        ]);

        $manager = new BillerManager($subscription->user, 'VAULT-ID-123');
        $manager->setProvider(PaymentProvider::PAYPAL);
        $manager->setup();

        $manager = new BillerManager($subscription->user);
        $manager->setProvider(PaymentProvider::PAYPAL);
        $result = $manager->removePaymentMethod();

        $this->assertTrue($result);

        $this->assertDatabaseMissing('users_payment_methods', [
            'user_id' => $subscription->user_id,
            'provider' => PaymentProvider::PAYPAL,
        ]);
    }

    /**
     * Test charging a Payable entity via PayPal using Vault ID.
     */
    #[Test]
    public function test_charge_paypal_auto_renewal()
    {
        $plan = Plan::factory()->create(['price' => 25.00]);
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::PAYPAL,
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $order = Order::factory()->create([
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'grand_total' => 25.00,
        ]);

        $manager = new BillerManager($subscription->user, 'VAULT-ID-123');
        $manager->setProvider(PaymentProvider::PAYPAL);
        $manager->setup();

        $paypal = $this->mockPaypal();

        $paypal->shouldReceive('createOrder')->once()->andReturn([
            'id' => 'ORDER-123',
            'status' => 'CREATED',
        ]);

        $paypal->shouldReceive('capturePaymentOrder')->once()->with('ORDER-123')->andReturn([
            'id' => 'CAPTURE-123',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'payments' => [
                        'captures' => [
                            [
                                'id' => 'CAPTURE-123',
                                'amount' => [
                                    'currency_code' => 'USD',
                                    'value' => '25.00',
                                ],
                                'status' => 'COMPLETED',
                                'create_time' => '2023-01-01T00:00:00Z',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $payable = Payable::fromOrder($order);
        $manager = new BillerManager($subscription->user);
        $result = $manager->charge($payable);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('CAPTURE-123', $result->getTransactionId());
        $this->assertEquals('COMPLETED', $result->getStatus());
    }

    /**
     * Test PayPal charge failure handling.
     */
    #[Test]
    public function test_paypal_charge_failure()
    {
        $plan = Plan::factory()->create(['price' => 25.00]);
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::PAYPAL,
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $order = Order::factory()->create([
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'grand_total' => 25.00,
        ]);

        $manager = new BillerManager($subscription->user, 'VAULT-ID-123');
        $manager->setProvider(PaymentProvider::PAYPAL);
        $manager->setup();

        $paypal = $this->mockPaypal();

        $paypal->shouldReceive('createOrder')->once()->andReturn([
            'id' => 'ORDER-123',
            'status' => 'CREATED',
        ]);

        $paypal->shouldReceive('capturePaymentOrder')->once()->with('ORDER-123')->andReturn([
            'id' => 'CAPTURE-123',
            'status' => 'FAILED',
        ]);

        $this->expectException(PaymentException::class);

        $payable = Payable::fromOrder($order);
        $manager = new BillerManager($subscription->user);
        $manager->charge($payable);
    }
}
