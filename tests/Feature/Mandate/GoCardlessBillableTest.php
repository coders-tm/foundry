<?php

namespace Tests\Feature\Mandate;

use Foundry\Mandate\BillerManager;
use Foundry\Mandate\Services\GoCardlessPayment;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class GoCardlessBillableTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('foundry.payment_providers.gocardless.access_token')) || config('foundry.payment_providers.gocardless.access_token') === 'test_gocardless_access_token') {
            $this->markTestSkipped('GoCardless API keys not configured.');
        }
    }

    /**
     * Test setup and removal of GoCardless payment method.
     */
    #[Test]
    public function test_auto_renewal_for_gocardless()
    {
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::GOCARDLESS,
            'auto_renewal_enabled' => true,
        ]);
        $paymentMethod = 'MD123456';

        $this->mock(GoCardlessPayment::class, function ($mock) {
            $mock->shouldReceive('setup')->once()->andReturn(true);
            $mock->shouldReceive('remove')->once()->andReturn(true);
        });

        $manager = new BillerManager($subscription->user, $paymentMethod);
        $manager->setProvider(PaymentProvider::GOCARDLESS);
        $result = $manager->setup();

        $this->assertTrue((bool) $result);

        $manager = new BillerManager($subscription->user, $paymentMethod);
        $manager->setProvider(PaymentProvider::GOCARDLESS);
        $result = $manager->removePaymentMethod();

        $this->assertTrue((bool) $result);
    }

    /**
     * Test charging a GoCardless payment.
     */
    #[Test]
    public function test_auto_renewal_charge_for_gocardless()
    {
        $plan = Plan::factory()->create(['price' => 100]);
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::GOCARDLESS,
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $order = Order::factory()->create([
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'grand_total' => 100.00,
        ]);

        $paymentMethod = 'MD123456';

        $this->mock(GoCardlessPayment::class, function ($mock) {
            $mock->shouldReceive('setup')->once()->andReturn(true);
            $mock->shouldReceive('charge')->once()->andReturn(PaymentResult::success(
                paymentData: null,
                transactionId: 'PM123456',
                status: 'paid'
            ));
        });

        $manager = new BillerManager($subscription->user, $paymentMethod);
        $manager->setProvider(PaymentProvider::GOCARDLESS);
        $manager->setup();

        $payable = Payable::fromOrder($order);
        $manager = new BillerManager($subscription->user);
        $result = $manager->charge($payable);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('paid', $result->getStatus());
    }
}
