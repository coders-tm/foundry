<?php

namespace Foundry\Tests\Feature\Subscription;

use Foundry\Contracts\ManagesSubscriptions;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Coupon;
use Foundry\Models\Subscription\Feature;
use Foundry\Tests\TestCase;
use Illuminate\Support\Carbon;

/**
 * Test the complete subscription lifecycle with states, grace periods,
 * and contract cycles.
 */
class SubscriptionLifecycleTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /**
     * Test subscription creation with PENDING status and invoice generation.
     */
    public function test_subscription_starts_in_pending_status()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $subscription->refresh();

        $this->assertEquals(SubscriptionStatus::PENDING, $subscription->status);
        $this->assertNotNull($subscription->latestInvoice);
        $this->assertFalse($subscription->latestInvoice->is_paid);
    }

    /**
     * Test subscription creation with coupon.
     */
    public function test_subscription_creation_with_coupon()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);
        $coupon = Coupon::factory()->create([
            'promotion_code' => 'TEST20',
            'discount_type' => 'percentage',
            'value' => 20,
        ]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->withCoupon('TEST20')
            ->saveAndInvoice([], true);

        $this->assertEquals($coupon->id, $subscription->coupon_id);
    }

    /**
     * Test creation with trial generates no initial invoice.
     */
    public function test_creation_with_trial_generates_no_initial_invoice()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 14]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->trialDays(14)
            ->saveAndInvoice();

        $this->assertTrue($subscription->onTrial());
        $this->assertNull($subscription->latestInvoice);
    }

    /**
     * Test transition from TRIALING to ACTIVE on manual trial end.
     */
    public function test_manual_trial_end_triggers_invoice()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 14]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->trialDays(14)
            ->saveAndInvoice();

        $this->assertTrue($subscription->onTrial());
        $this->assertEquals(SubscriptionStatus::TRIALING, $subscription->status);

        // End trial manually
        $subscription->endTrial();
        $this->assertFalse($subscription->onTrial());

        // Renew/Invoice after trial end
        $subscription->saveAndInvoice([], true)->refresh();

        $this->assertNotNull($subscription->latestInvoice);
    }

    /**
     * Test subscription cancellation and resumption workflow.
     */
    public function test_subscription_cancellation_and_resumption_workflow()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $this->assertNull($subscription->canceled_at);
        $this->assertFalse($subscription->canceled());

        // Cancel subscription
        $subscription->cancel();

        $this->assertNotNull($subscription->canceled_at);
        $this->assertTrue($subscription->canceled());
        $this->assertTrue($subscription->canceledOnGracePeriod());

        // Resume subscription
        $subscription->resume();

        $this->assertNull($subscription->canceled_at);
        $this->assertFalse($subscription->canceled());
        $this->assertTrue($subscription->active());
    }

    /**
     * Test subscription immediate cancellation workflow.
     */
    public function test_subscription_immediate_cancellation_workflow()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        // Cancel immediately
        $subscription->cancelNow();

        $this->assertTrue($subscription->canceled());
        $this->assertFalse($subscription->canceledOnGracePeriod());
        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->status);
    }

    /**
     * Test subscription with multiple method chaining.
     */
    public function test_subscription_with_multiple_method_chaining()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 14]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->trialDays(14)
            ->skipTrial()
            ->saveAndInvoice([], true)
            ->refresh();

        $this->assertTrue($subscription->exists);
        $this->assertFalse($subscription->onTrial());
        $this->assertNotNull($subscription->latestInvoice);
    }

    /**
     * Test subscription implements ManagesSubscriptions interface.
     */
    public function test_subscription_implements_manages_subscriptions_interface()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $this->assertInstanceOf(ManagesSubscriptions::class, $subscription);
        $this->assertTrue(method_exists($subscription, 'valid'));
        $this->assertTrue(method_exists($subscription, 'swap'));
        $this->assertTrue(method_exists($subscription, 'cancel'));
    }

    /**
     * Test subscription downgrade workflow and cancellation.
     */
    public function test_subscription_downgrade_workflow()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $proPlan = (Foundry::$planModel)::factory()->create(['price' => 2000, 'label' => 'Pro']);
        $basicPlan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'label' => 'Basic']);

        $subscription = $user->newSubscription('default', $proPlan->id)
            ->saveAndInvoice([], true);

        // Downgrade to basic plan
        $subscription->next_plan = $basicPlan->id;
        $subscription->is_downgrade = true;
        $subscription->save();

        $this->assertTrue($subscription->hasDowngrade());
        $this->assertEquals($basicPlan->id, $subscription->next_plan);

        // Cancel downgrade
        $subscription->cancelDowngrade();

        $this->assertFalse($subscription->hasDowngrade());
        $this->assertNull($subscription->next_plan);
    }

    /**
     * Test subscription status becomes INCOMPLETE on payment failure.
     */
    public function test_subscription_status_becomes_incomplete_on_payment_failure()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        // Simulate payment failure
        $subscription->paymentFailed();

        $this->assertEquals(SubscriptionStatus::INCOMPLETE, $subscription->status);
        $this->assertTrue($subscription->hasIncompletePayment());
    }

    /**
     * Test cancellation of open invoices.
     */
    public function test_subscription_cancel_open_invoices_workflow()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        // Generate another open invoice
        $subscription->generateInvoice(true);

        // Cancel all open invoices
        $subscription->cancelOpenInvoices();

        $this->assertEquals(0, $subscription->invoices()->where('status', 'open')->count());
    }

    /**
     * Test transition from PENDING to ACTIVE on payment.
     */
    public function test_subscription_transitions_to_active_on_payment()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $this->assertEquals(SubscriptionStatus::PENDING, $subscription->status);

        // Simulate payment confirmation
        $subscription->paymentConfirmation();

        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->ends_at);
    }

    /**
     * Test subscription stays ACTIVE when payment fails but enters grace period.
     */
    public function test_active_subscription_enters_grace_on_payment_failure()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $subscription->paymentConfirmation();
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);

        // Set expires_at to future (next billing period) and ends_at to near future (within grace period)
        // This simulates the renewal scenario where customer hasn't paid yet
        $subscription->expires_at = now()->addMonth(); // Next billing period
        $subscription->ends_at = now()->addDays(6); // Grace period end (before expires_at)
        $subscription->save();

        // Simulate payment failure
        $subscription->paymentFailed();

        // Status stays ACTIVE, but onGracePeriod() returns true
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->notOnGracePeriod());
    }

    /**
     * Test subscription generates invoice with ACTIVE status when renewing without payment.
     */
    public function test_renewal_without_payment_sets_grace_status()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $subscription->paymentConfirmation();

        // Generate invoice (renewal scenario)
        $invoice = $subscription->generateInvoice();

        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNotNull($invoice);
        $this->assertFalse($invoice->is_paid);
    }

    /**
     * Test grace period status is recognized as valid.
     */
    public function test_grace_status_is_valid()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $subscription->paymentConfirmation();

        // Set up subscription in grace period (after renewal, waiting for payment)
        $subscription->status = SubscriptionStatus::ACTIVE;
        $subscription->starts_at = now();
        $subscription->expires_at = now()->addMonth(); // Next billing period
        $subscription->ends_at = now()->addDays(7); // Grace period ends in 7 days (before expires_at)
        $subscription->save();

        $this->assertTrue($subscription->onGracePeriod());
        $this->assertTrue($subscription->valid());
    }

    /**
     * Test payment during grace period transitions back to fully ACTIVE.
     */
    public function test_payment_during_grace_reactivates_subscription()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        // Create plan with grace period enabled (7 days)
        $plan = (Foundry::$planModel)::factory()->withGracePeriod(7)->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)->saveAndInvoice([], true);
        $subscription->paymentConfirmation();

        // Simulate renewal that enters grace period (unpaid)
        $subscription->renew(); // This creates new period with grace

        $this->assertTrue($subscription->onGracePeriod());
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);

        // Simulate payment during grace period - should exit grace
        $subscription->paymentConfirmation();

        // After payment, subscription should no longer be in grace
        // Payment confirmation should clear ends_at (grace period)
        $subscription->refresh();

        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertNull($subscription->ends_at); // Grace period cleared by payment
        $this->assertFalse($subscription->onGracePeriod()); // No longer in grace (payment made)
        $this->assertTrue($subscription->expires_at->isFuture());
    }

    /**
     * Test billing interval is stored.
     */
    public function test_billing_interval_is_stored()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'interval' => 'month', 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $this->assertEquals('month', $subscription->billing_interval);
    }

    /**
     * Test grace period scope query.
     */
    public function test_grace_scope_filters_subscriptions()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        // Create subscriptions in different states
        $activeSubscription = $user->newSubscription('active', $plan->id);
        $activeSubscription->status = SubscriptionStatus::ACTIVE;
        $activeSubscription->expires_at = now()->addMonth(); // Not expired
        $activeSubscription->save();

        $graceSubscription = $user->newSubscription('grace', $plan->id);
        $graceSubscription->status = SubscriptionStatus::ACTIVE;
        $graceSubscription->expires_at = now()->addMonth(); // Next billing period
        $graceSubscription->ends_at = now()->addDays(4); // Grace period ends in 4 days (before expires_at)
        $graceSubscription->save();

        $expiredSubscription = $user->newSubscription('expired', $plan->id);
        $expiredSubscription->status = SubscriptionStatus::EXPIRED;
        $expiredSubscription->save();

        // Query only grace period subscriptions
        $graceSubscriptions = (Foundry::$subscriptionModel)::query()->onGracePeriod()->get();

        $this->assertCount(1, $graceSubscriptions);
        $this->assertEquals($graceSubscription->id, $graceSubscriptions->first()->id);

        // Test notOnGracePeriod scope
        $notGraceSubscriptions = (Foundry::$subscriptionModel)::query()->notOnGracePeriod()->get();
        $this->assertGreaterThanOrEqual(2, $notGraceSubscriptions->count());
        $this->assertFalse($notGraceSubscriptions->contains($graceSubscription));
    }

    /**
     * Test cannot renew subscription that has reached contract limit.
     */
    public function test_cannot_renew_beyond_contract_cycles()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['price' => 1000, 'trial_days' => 0]);

        $subscription = $user->newSubscription('default', $plan->id)
            ->contractCycles(1)
            ->saveAndInvoice([], true);

        $subscription->paymentConfirmation();
        $subscription->renew(); // Completes contract and cancels

        // Verify it's canceled
        $this->assertEquals(SubscriptionStatus::CANCELED, $subscription->status);
        $this->assertTrue($subscription->contractComplete());

        // Try to renew again - should throw exception because it's ended
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to renew canceled ended subscription');

        $subscription->renew();
    }

    /**
     * Test renewing a subscription clears the trial_ends_at date.
     */
    public function test_renew_clears_trial_ends_at()
    {
        // 1. Create a subscription that is currently on trial (or just ended trial)
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create([
            'trial_days' => 14,
            'interval' => 'month',
            'price' => 1000,
        ]);

        // Create subscription manually to simulate state just before renewal
        $trialEndAndExpires = Carbon::now()->subMinute(); // Just passed

        $subscription = (Foundry::$subscriptionModel)::create([
            'type' => 'default',
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => $trialEndAndExpires,
            'starts_at' => $trialEndAndExpires, // Billing starts when trial ends
            'expires_at' => $trialEndAndExpires, // Expires when billing period (trial) ends
        ]);

        // 2. Refresh to make sure
        $subscription->refresh();
        $this->assertEquals($trialEndAndExpires->toDateTimeString(), $subscription->trial_ends_at->toDateTimeString());

        // 3. Call renew()
        $subscription->renew();

        // 4. Assert trial_ends_at is NULL
        $this->assertNull($subscription->trial_ends_at, 'trial_ends_at should be null after renewal');

        // 5. Assert Invoice is generated
        $this->assertCount(1, $subscription->invoices()->get(), 'An invoice should be generated upon renewal');
    }

    public function test_renewal_extends_expires_at_by_plan_interval()
    {
        $plan = (Foundry::$planModel)::factory()->create([
            'interval' => 'month',
            'interval_count' => 1,
            'price' => 1000,
        ]);
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $originalExpiresAt = Carbon::parse('2025-02-01');

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => Carbon::parse('2025-01-01'),
            'expires_at' => $originalExpiresAt,
        ]);
        $subscription->save();

        $subscription->renew();

        $expectedNewExpiry = $originalExpiresAt->copy()->addMonth();
        $this->assertEquals($expectedNewExpiry->format('Y-m-d'), $subscription->expires_at->format('Y-m-d'));
    }

    public function test_renewal_with_different_interval_counts()
    {
        $plan = (Foundry::$planModel)::factory()->create([
            'interval' => 'month',
            'interval_count' => 3,
            'price' => 3000,
        ]);
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $originalExpiresAt = Carbon::parse('2025-01-01');

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => Carbon::parse('2024-10-01'),
            'expires_at' => $originalExpiresAt,
        ]);
        $subscription->save();

        $subscription->renew();

        $expectedNewExpiry = $originalExpiresAt->copy()->addMonths(3);
        $this->assertEquals($expectedNewExpiry->format('Y-m-d'), $subscription->expires_at->format('Y-m-d'));
    }

    public function test_renewal_with_yearly_plan()
    {
        $plan = (Foundry::$planModel)::factory()->create([
            'interval' => 'year',
            'interval_count' => 1,
            'price' => 12000,
        ]);
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $originalExpiresAt = Carbon::parse('2025-01-01');

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => Carbon::parse('2024-01-01'),
            'expires_at' => $originalExpiresAt,
        ]);
        $subscription->save();

        $subscription->renew();

        $expectedNewExpiry = $originalExpiresAt->copy()->addYear();
        $this->assertEquals($expectedNewExpiry->format('Y-m-d'), $subscription->expires_at->format('Y-m-d'));
    }

    public function test_renewal_with_next_plan_updates_billing_fields()
    {
        $monthlyPlan = (Foundry::$planModel)::factory()->create([
            'interval' => 'month',
            'interval_count' => 1,
            'price' => 1000,
        ]);
        $quarterlyContractPlan = (Foundry::$planModel)::factory()->create([
            'interval' => 'month',
            'interval_count' => 3,
            'price' => 2500,
            'is_contract' => true,
            'contract_cycles' => 4,
        ]);
        $user = (Foundry::$subscriptionUserModel)::factory()->create();

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'plan_id' => $monthlyPlan->id,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'total_cycles' => null,
            'current_cycle' => 3,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => Carbon::parse('2025-01-01'),
            'expires_at' => Carbon::parse('2025-04-01'),
        ]);
        $subscription->save();

        $subscription->next_plan = $quarterlyContractPlan->id;
        $subscription->is_downgrade = true;
        $subscription->save();

        $subscription->renew();
        $subscription->refresh();

        $this->assertEquals('month', $subscription->billing_interval);
        $this->assertEquals(3, $subscription->billing_interval_count);
        $this->assertEquals(4, $subscription->total_cycles);
        $this->assertEquals(1, $subscription->current_cycle);
        $this->assertEquals($quarterlyContractPlan->id, $subscription->plan_id);

        $expectedExpiresAt = Carbon::parse('2025-04-01')->addMonths(3);
        $this->assertEquals($expectedExpiresAt->format('Y-m-d'), $subscription->expires_at->format('Y-m-d'));
    }

    public function test_renewal_uses_plan_grace_period_days()
    {
        $plan = (Foundry::$planModel)::factory()->withGracePeriod(14)->create(['price' => 1000]);
        $user = (Foundry::$subscriptionUserModel)::factory()->create();

        $originalExpiresAt = Carbon::parse('2025-01-01');

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => Carbon::parse('2024-12-01'),
            'expires_at' => $originalExpiresAt,
        ]);
        $subscription->save();

        Carbon::setTestNow('2025-01-01 12:00:00');

        $subscription->renew();

        $expectedGraceEnd = Carbon::now()->addDays(14);
        $this->assertNotNull($subscription->ends_at);
        $this->assertEquals($expectedGraceEnd->format('Y-m-d H:i'), $subscription->ends_at->format('Y-m-d H:i'));

        Carbon::setTestNow(); // Reset
    }

    public function test_renewal_with_zero_grace_period_expires_immediately()
    {
        $plan = (Foundry::$planModel)::factory()->withGracePeriod(0)->create(['price' => 1000]);
        $user = (Foundry::$subscriptionUserModel)::factory()->create();

        $subscription = new (Foundry::$subscriptionModel)([
            'user_id' => $user->id,
            'type' => 'default',
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => Carbon::parse('2024-12-01'),
            'expires_at' => Carbon::parse('2025-01-01'),
        ]);
        $subscription->save();

        $subscription->renew();

        $this->assertNull($subscription->ends_at);
        $this->assertEquals(SubscriptionStatus::EXPIRED, $subscription->status);
    }

    public function test_artisan_command_renews_active_subscriptions()
    {
        (Foundry::$planModel)::factory()->create();
        $subscription = (Foundry::$subscriptionModel)::withoutEvents(function () {
            return (Foundry::$subscriptionModel)::factory()->create(['expires_at' => now()->subDay()]);
        });

        $this->artisan('foundry:subscriptions-renew')->assertExitCode(0);

        $this->assertDatabaseHas('logs', [
            'type' => 'renew',
            'logable_type' => 'Subscription',
            'logable_id' => $subscription->id,
        ]);
    }

    public function test_artisan_command_logs_an_error_when_renewal_fails()
    {
        (Foundry::$subscriptionModel)::withoutEvents(function () {
            return (Foundry::$subscriptionModel)::factory()->create(['expires_at' => now()->subDay()]);
        });

        $this->partialMock(Foundry::$subscriptionModel, function ($mock) {
            $mock->shouldReceive('renew')->andThrow(new \Exception('Renewal failed'));
        });

        $this->artisan('foundry:subscriptions-renew')->assertExitCode(0);
    }

    public function test_artisan_command_renews_trialing_subscriptions_that_have_expired()
    {
        Carbon::setTestNow(null);
        $plan = (Foundry::$planModel)::factory()->create(['grace_period_days' => 0, 'price' => 1000]);
        $subscription = (Foundry::$subscriptionModel)::withoutEvents(function () use ($plan) {
            return (Foundry::$subscriptionModel)::factory()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::TRIALING,
                'trial_ends_at' => now()->subDay(),
                'expires_at' => now()->subDay(),
            ]);
        });

        $this->artisan('foundry:subscriptions-renew')->assertExitCode(0);

        $this->assertDatabaseHas('logs', [
            'type' => 'renew',
            'logable_type' => 'Subscription',
            'logable_id' => $subscription->id,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::EXPIRED,
        ]);
    }

    public function test_does_not_reset_non_resetable_features_on_renewal()
    {
        $user = (Foundry::$subscriptionUserModel)::factory()->create();
        $plan = (Foundry::$planModel)::factory()->create(['trial_days' => 0]);

        $resetableFeature = Feature::factory()->create([
            'slug' => 'api-calls',
            'type' => 'integer',
            'resetable' => true,
        ]);
        $nonResetableFeature = Feature::factory()->create([
            'slug' => 'storage-used',
            'type' => 'integer',
            'resetable' => false,
        ]);

        $plan->features()->attach($resetableFeature->id, ['value' => 1000]);
        $plan->features()->attach($nonResetableFeature->id, ['value' => 5000]);

        $subscription = $user->newSubscription('default', $plan->id)->saveAndInvoice([], true);
        $subscription->recordFeatureUsage('api-calls', 500);
        $subscription->recordFeatureUsage('storage-used', 2500);

        $subscription->update(['expires_at' => now()->subDay()]);
        $subscription->renew();

        $this->assertEquals(0, $subscription->getFeatureUsage('api-calls'));
        $this->assertEquals(2500, $subscription->getFeatureUsage('storage-used'));
    }
}
