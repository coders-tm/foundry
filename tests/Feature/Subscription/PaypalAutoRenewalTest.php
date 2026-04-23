<?php

namespace Tests\Feature\Subscription;

use Foundry\AutoRenewal\AutoRenewalManager;
use Foundry\AutoRenewal\Payments\PaypalPayment;
use Foundry\Foundry;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Srmklive\PayPal\Services\PayPal;

class PaypalAutoRenewalTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset the static PayPal client in Foundry after each test
        $this->setStaticPaypalClient(null);
        parent::tearDown();
    }

    /**
     * Helper to mock the PayPal SDK and inject it into Foundry.
     */
    protected function mockPaypal()
    {
        $mock = $this->mock(PayPal::class);
        $mock->shouldReceive('setApiCredentials')->andReturnSelf();
        $mock->shouldReceive('getAccessToken')->andReturn(['access_token' => 'mock-token']);

        $this->setStaticPaypalClient($mock);

        return $mock;
    }

    /**
     * Set the static paypalClient property in Foundry via reflection.
     */
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
        $subscription = Subscription::factory()->create(['provider' => 'paypal']);

        $manager = new AutoRenewalManager($subscription, 'VAULT-ID-123');
        $manager->setProvider('paypal');
        $result = $manager->setup();

        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals('paypal', $result->provider);
        $this->assertEquals(1, $result->auto_renewal_enabled);

        // Verify models were created
        $this->assertDatabaseHas('payment_provider_customers', [
            'user_id' => $subscription->user_id,
            'provider' => 'paypal',
        ]);

        $this->assertDatabaseHas('users_payment_methods', [
            'user_id' => $subscription->user_id,
            'provider' => 'paypal',
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
            'provider' => 'paypal',
            'auto_renewal_enabled' => true,
        ]);

        // Setup first
        $manager = new AutoRenewalManager($subscription, 'VAULT-ID-123');
        $manager->setup();

        // Then remove
        $manager = new AutoRenewalManager($subscription);
        $result = $manager->remove();

        $this->assertEquals(0, $result->auto_renewal_enabled);
        $this->assertFalse($result->fresh()->auto_renewal_enabled);

        $this->assertDatabaseMissing('users_payment_methods', [
            'user_id' => $subscription->user_id,
            'provider' => 'paypal',
        ]);
    }

    /**
     * Test auto-renewal charge for PayPal using realistic mocking.
     */
    #[Test]
    public function test_charge_paypal_auto_renewal()
    {
        $plan = Plan::factory()->create(['price' => 25.00]);
        $subscription = Subscription::factory()->create([
            'provider' => 'paypal',
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        // Setup payment method
        $manager = new AutoRenewalManager($subscription, 'VAULT-ID-123');
        $manager->setup();

        // Mock the PayPal SDK
        $paypal = $this->mockPaypal();

        // Realistic order creation response
        $paypal->shouldReceive('createOrder')->once()->andReturn([
            'id' => 'ORDER-123',
            'status' => 'CREATED',
        ]);

        // Realistic capture response matching PaypalPayment expectations
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

        $manager = new AutoRenewalManager($subscription);
        $payment = $manager->charge();

        $this->assertInstanceOf(PaypalPayment::class, $payment);
        $this->assertEquals('CAPTURE-123', $payment->id());
        $this->assertEquals('succeeded', $payment->status());
        $this->assertEquals(2500, $payment->amount());
        $this->assertEquals('USD', $payment->currency());
    }

    /**
     * Test PayPal charge failure handling.
     */
    #[Test]
    public function test_paypal_charge_failure()
    {
        $plan = Plan::factory()->create(['price' => 25.00]);
        $subscription = Subscription::factory()->create([
            'provider' => 'paypal',
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $manager = new AutoRenewalManager($subscription, 'VAULT-ID-123');
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PayPal charge failed');

        $manager = new AutoRenewalManager($subscription);
        $manager->charge();
    }

    /**
     * Test mapping of PayPal payment response.
     */
    #[Test]
    public function test_paypal_payment_normalization()
    {
        $data = [
            'id' => 'CAPTURE-123',
            'status' => 'COMPLETED',
            'amount' => [
                'currency_code' => 'USD',
                'value' => '10.00',
            ],
            'create_time' => '2023-01-01T00:00:00Z',
        ];

        $payment = new PaypalPayment($data);

        $this->assertEquals('CAPTURE-123', $payment->id());
        $this->assertEquals('succeeded', $payment->status());
        $this->assertEquals(1000, $payment->amount());
        $this->assertEquals('USD', $payment->currency());
    }
}
