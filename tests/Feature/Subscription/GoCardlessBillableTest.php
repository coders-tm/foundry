<?php

namespace Tests\Feature\Subscription;

use Foundry\Billable\BillableManager;
use Foundry\Billable\Services\GoCardlessPayment;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class GoCardlessBillableTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        if (empty(config('gocardless.secret'))) {
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
            'provider' => 'gocardless',
            'auto_renewal_enabled' => true,
        ]);
        $paymentMethod = 'MD123456';

        $this->mock(GoCardlessPayment::class, function ($mock) {
            $mock->shouldReceive('setup')->once()->andReturn(true);
            $mock->shouldReceive('remove')->once()->andReturn(true);
        });

        $manager = new BillableManager($subscription->user, $paymentMethod);
        $manager->setProvider('gocardless');
        $result = $manager->setup();

        $this->assertTrue((bool) $result);

        $manager = new BillableManager($subscription->user, $paymentMethod);
        $manager->setProvider('gocardless');
        $result = $manager->remove();

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
            'provider' => 'gocardless',
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

        $manager = new BillableManager($subscription->user, $paymentMethod);
        $manager->setProvider('gocardless');
        $manager->setup();

        $payable = Payable::fromOrder($order);
        $manager = new BillableManager($subscription->user);
        $result = $manager->charge($payable);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('paid', $result->getStatus());
    }
}
