<?php

namespace Foundry\Tests\Feature\Subscription;

use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Services\Admin\SubscriptionService;
use Foundry\Tests\TestCase;

/**
 * Tests that subscription starts_at and expires_at are set correctly
 * when updating via SubscriptionService, both with and without mark_as_paid.
 */
class SubscriptionDatePeriodTest extends TestCase
{
    protected User $user;

    protected Plan $monthlyPlan;

    protected Plan $yearlyPlan;

    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Fixed-interval plans for deterministic date assertions
        $this->monthlyPlan = Plan::factory()->create([
            'label' => 'Monthly',
            'interval' => 'month',
            'interval_count' => 1,
            'price' => 10.00,
            'trial_days' => 0,
        ]);

        $this->yearlyPlan = Plan::factory()->create([
            'label' => 'Yearly',
            'interval' => 'year',
            'interval_count' => 1,
            'price' => 100.00,
            'trial_days' => 0,
        ]);

        $this->paymentMethod = PaymentMethod::first();
    }

    /**
     * Create an active subscription on monthlyPlan so we can swap it to yearlyPlan.
     */
    protected function createActiveSubscription(): Subscription
    {
        return Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $this->monthlyPlan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addDays(5),
        ]);
    }

    // -------------------------------------------------------------------------
    // mark_as_paid = false
    // -------------------------------------------------------------------------

    /**
     * When only starts_at is supplied (no expires_at), expires_at must be
     * auto-calculated as starts_at + plan interval.
     */
    public function test_update_without_mark_as_paid_custom_starts_at_auto_calculates_expires_at(): void
    {
        $subscription = $this->createActiveSubscription();
        $customStart = Carbon::parse('2026-06-01');

        $service = app(SubscriptionService::class);
        $updated = $service->createOrUpdate($this->user, [
            'plan' => $this->yearlyPlan->id,
            'starts_at' => $customStart->toDateTimeString(),
        ], $subscription);

        // starts_at must equal the supplied date
        $this->assertEquals(
            $customStart->format('Y-m-d'),
            $updated->starts_at->format('Y-m-d'),
            'starts_at should be the custom value'
        );

        // expires_at must be exactly starts_at + 1 year (plan interval)
        $this->assertEquals(
            $customStart->copy()->addYear()->format('Y-m-d'),
            $updated->expires_at->format('Y-m-d'),
            'expires_at should be auto-calculated as starts_at + plan interval'
        );
    }

    /**
     * When both starts_at and expires_at are supplied, both custom values are preserved.
     */
    public function test_update_without_mark_as_paid_preserves_both_custom_dates(): void
    {
        $subscription = $this->createActiveSubscription();
        $customStart = Carbon::parse('2026-06-01');
        $customExpiry = Carbon::parse('2026-09-01'); // not aligned with plan interval

        $service = app(SubscriptionService::class);
        $updated = $service->createOrUpdate($this->user, [
            'plan' => $this->yearlyPlan->id,
            'starts_at' => $customStart->toDateTimeString(),
            'expires_at' => $customExpiry->toDateTimeString(),
        ], $subscription);

        $this->assertEquals(
            $customStart->format('Y-m-d'),
            $updated->starts_at->format('Y-m-d'),
            'starts_at should be preserved when explicitly provided'
        );

        $this->assertEquals(
            $customExpiry->format('Y-m-d'),
            $updated->expires_at->format('Y-m-d'),
            'expires_at should be preserved when explicitly provided'
        );
    }

    /**
     * When no custom dates are provided, setPeriod() calculates expires_at from
     * starts_at + plan interval. With anchor_from_invoice=true (default), starts_at
     * stays anchored to the original subscription start date.
     */
    public function test_update_without_mark_as_paid_no_custom_dates_sets_expires_at_from_starts_at(): void
    {
        $subscription = $this->createActiveSubscription();
        $originalStartsAt = $subscription->starts_at->copy();

        $service = app(SubscriptionService::class);
        $updated = $service->createOrUpdate($this->user, [
            'plan' => $this->yearlyPlan->id,
        ], $subscription);

        $this->assertNotNull($updated->starts_at, 'starts_at must be set');
        $this->assertNotNull($updated->expires_at, 'expires_at must be set');

        // expires_at must equal starts_at + 1 year (plan interval)
        $this->assertEquals(
            $updated->starts_at->copy()->addYear()->format('Y-m-d'),
            $updated->expires_at->format('Y-m-d'),
            'expires_at must be starts_at + plan interval'
        );
    }

    // -------------------------------------------------------------------------
    // mark_as_paid = true
    // -------------------------------------------------------------------------

    /**
     * When mark_as_paid is true and only starts_at is supplied, expires_at must
     * be auto-calculated as starts_at + plan interval and subscription goes active.
     */
    public function test_update_with_mark_as_paid_custom_starts_at_auto_calculates_expires_at(): void
    {
        $subscription = $this->createActiveSubscription();
        $customStart = Carbon::parse('2026-06-01');

        $service = app(SubscriptionService::class);
        $updated = $service->createOrUpdate($this->user, [
            'plan' => $this->yearlyPlan->id,
            'starts_at' => $customStart->toDateTimeString(),
            'mark_as_paid' => true,
            'payment_method' => $this->paymentMethod->id,
        ], $subscription);

        $this->assertEquals(
            $customStart->format('Y-m-d'),
            $updated->starts_at->format('Y-m-d'),
            'starts_at should equal the custom value'
        );

        $this->assertEquals(
            $customStart->copy()->addYear()->format('Y-m-d'),
            $updated->expires_at->format('Y-m-d'),
            'expires_at should be auto-calculated as starts_at + plan interval'
        );

        $this->assertEquals(
            SubscriptionStatus::ACTIVE,
            $updated->status,
            'Subscription should be active after mark_as_paid'
        );
    }

    /**
     * When mark_as_paid is true and both dates are supplied, both are preserved
     * and the subscription goes active.
     */
    public function test_update_with_mark_as_paid_preserves_both_custom_dates(): void
    {
        $subscription = $this->createActiveSubscription();
        $customStart = Carbon::parse('2026-06-01');
        $customExpiry = Carbon::parse('2026-09-01');

        $service = app(SubscriptionService::class);
        $updated = $service->createOrUpdate($this->user, [
            'plan' => $this->yearlyPlan->id,
            'starts_at' => $customStart->toDateTimeString(),
            'expires_at' => $customExpiry->toDateTimeString(),
            'mark_as_paid' => true,
            'payment_method' => $this->paymentMethod->id,
        ], $subscription);

        $this->assertEquals(
            $customStart->format('Y-m-d'),
            $updated->starts_at->format('Y-m-d'),
            'starts_at should be preserved when explicitly provided'
        );

        $this->assertEquals(
            $customExpiry->format('Y-m-d'),
            $updated->expires_at->format('Y-m-d'),
            'expires_at should be preserved when explicitly provided'
        );

        $this->assertEquals(SubscriptionStatus::ACTIVE, $updated->status);
    }

    /**
     * When mark_as_paid is true and no custom dates are provided, expires_at is
     * set as starts_at + plan interval and the subscription goes active.
     * With anchor_from_invoice=true (default), starts_at stays anchored.
     */
    public function test_update_with_mark_as_paid_no_custom_dates_sets_expires_at_from_starts_at(): void
    {
        $subscription = $this->createActiveSubscription();

        $service = app(SubscriptionService::class);
        $updated = $service->createOrUpdate($this->user, [
            'plan' => $this->yearlyPlan->id,
            'mark_as_paid' => true,
            'payment_method' => $this->paymentMethod->id,
        ], $subscription);

        $this->assertNotNull($updated->starts_at, 'starts_at must be set');
        $this->assertNotNull($updated->expires_at, 'expires_at must be set');

        // expires_at must equal starts_at + 1 year (plan interval)
        $this->assertEquals(
            $updated->starts_at->copy()->addYear()->format('Y-m-d'),
            $updated->expires_at->format('Y-m-d'),
            'expires_at must be starts_at + plan interval'
        );

        $this->assertEquals(
            SubscriptionStatus::ACTIVE,
            $updated->status,
            'Subscription must be active after mark_as_paid'
        );
    }

    /**
     * Verify the database rows reflect the correct dates after the service call.
     */
    public function test_database_reflects_correct_dates_after_update(): void
    {
        $subscription = $this->createActiveSubscription();
        $customStart = Carbon::parse('2026-07-01');

        $service = app(SubscriptionService::class);
        $service->createOrUpdate($this->user, [
            'plan' => $this->yearlyPlan->id,
            'starts_at' => $customStart->toDateTimeString(),
            'mark_as_paid' => true,
            'payment_method' => $this->paymentMethod->id,
        ], $subscription);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'starts_at' => $customStart->format('Y-m-d H:i:s'),
            'expires_at' => $customStart->copy()->addYear()->format('Y-m-d H:i:s'),
            'status' => SubscriptionStatus::ACTIVE,
        ]);
    }
}
