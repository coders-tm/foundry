<?php

namespace Tests\Feature\Subscription;

use Foundry\AutoRenewal\AutoRenewalManager;
use Foundry\AutoRenewal\Payments\GoCardlessPayment;
use Foundry\AutoRenewal\Services\GoCardlessSubscription;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class GoCardlessAutoRenewalTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip tests if Stripe keys are not configured
        if (empty(config('gocardless.secret'))) {
            $this->markTestSkipped('GoCardless API keys not configured.');
        }
    }

    /**
     * Test setup and removal of GoCardless auto-renewal.
     */
    #[Test]
    public function test_auto_renewal_for_gocardless()
    {
        $subscription = Subscription::factory()->create([
            'provider' => 'gocardless',
            'auto_renewal_enabled' => true,
        ]);
        $paymentMethod = 'MD123456';  // Mock mandate ID

        // Mock the GoCardlessSubscription class
        $this->mock(GoCardlessSubscription::class, function ($mock) use ($subscription) {
            $mock->shouldReceive('setup')->once()->andReturn($subscription);
            $mock->shouldReceive('remove')->once()->andReturn($subscription);
        });

        $manager = new AutoRenewalManager($subscription, $paymentMethod);
        $manager->setProvider('gocardless');
        $result = $manager->setup();

        $this->assertInstanceOf(Subscription::class, $result);
        $this->assertEquals('gocardless', $result->provider);

        $manager = new AutoRenewalManager($subscription, $paymentMethod);
        $result = $manager->remove();

        $this->assertInstanceOf(Subscription::class, $result);
    }

    /**
     * Test charging a GoCardless auto-renewal.
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
        $paymentMethod = 'MD123456';  // Mock mandate ID

        // Mock the GoCardlessSubscription charge method
        $this->mock(GoCardlessSubscription::class, function ($mock) {
            $mock->shouldReceive('setup')->once()->andReturn(true);
            $mock->shouldReceive('charge')->once()->andReturn(new GoCardlessPayment([
                'id' => 'PM123456',
                'amount' => 10000,
                'currency' => 'gbp',
                'status' => 'paid',
            ]));
        });

        $manager = new AutoRenewalManager($subscription, $paymentMethod);
        $manager->setProvider('gocardless');
        $manager->setup();

        $manager = new AutoRenewalManager($subscription);
        $result = $manager->charge();

        $this->assertInstanceOf(GoCardlessPayment::class, $result);
        $this->assertEquals('succeeded', $result->status());
    }
}
