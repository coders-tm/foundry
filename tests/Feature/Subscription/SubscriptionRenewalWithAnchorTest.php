<?php

namespace Foundry\Tests\Feature\Subscription;

use Foundry\Foundry;
use Foundry\Tests\TestCase;

/**
 * Test subscription renewal with anchor_from_invoice config enabled.
 *
 * This ensures that the anchor_from_invoice configuration doesn't
 * interfere with subscription renewal behavior.
 */
class SubscriptionRenewalWithAnchorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure anchor_from_invoice is enabled for these tests
        config(['foundry.subscription.anchor_from_invoice' => true]);
    }

    public function test_renewal_extends_period_when_anchor_from_invoice_is_enabled()
    {
        // Arrange: Create a monthly plan
        $plan = (Foundry::$planModel)::factory()->create([
            'interval' => 'month',
            'interval_count' => 1,
            'price' => 1000,
        ]);

        $user = (Foundry::$subscriptionUserModel)::factory()->create();

        // Create subscription via newSubscription->saveAndInvoice like in real workflow
        $subscription = $user->newSubscription('default', $plan->id);
        $subscription->saveAndInvoice([], true);

        $originalStartsAt = $subscription->starts_at;
        $originalExpiresAt = $subscription->expires_at;

        // Act: Renew the subscription
        $subscription->renew();

        // Assert: Both dates should change
        $this->assertNotEquals(
            $originalStartsAt->format('Y-m-d'),
            $subscription->starts_at->format('Y-m-d'),
            'starts_at should change after renewal'
        );

        $this->assertNotEquals(
            $originalExpiresAt->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d'),
            'expires_at should change after renewal'
        );

        // Assert: The new period should be approximately 1 month from original expiry
        $expectedNewStarts = $originalExpiresAt->copy();
        $expectedNewExpiry = $originalExpiresAt->copy()->addMonth();

        $this->assertEquals(
            $expectedNewStarts->format('Y-m-d'),
            $subscription->starts_at->format('Y-m-d'),
            'New starts_at should equal original expires_at'
        );

        $this->assertEquals(
            $expectedNewExpiry->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d'),
            'New expires_at should be 1 month after original expires_at'
        );
    }

    public function test_renewal_works_with_quarterly_plan_and_anchor_enabled()
    {
        // Arrange: Create a quarterly plan
        $plan = (Foundry::$planModel)::factory()->create([
            'interval' => 'month',
            'interval_count' => 3,
            'price' => 3000,
        ]);

        $user = (Foundry::$subscriptionUserModel)::factory()->create();

        $subscription = $user->newSubscription('default', $plan->id);
        $subscription->saveAndInvoice([], true);

        $originalExpiresAt = $subscription->expires_at;

        // Act: Renew the subscription
        $subscription->renew();

        // Assert: expires_at should be extended by 3 months
        $expectedNewExpiry = $originalExpiresAt->copy()->addMonths(3);
        $this->assertEquals(
            $expectedNewExpiry->format('Y-m-d'),
            $subscription->expires_at->format('Y-m-d'),
            'expires_at should be extended by 3 months from original expiry'
        );
    }

    public function test_multiple_renewals_properly_advance_period()
    {
        // Arrange: Create a monthly plan
        $plan = (Foundry::$planModel)::factory()->create([
            'interval' => 'month',
            'interval_count' => 1,
            'price' => 1000,
        ]);

        $user = (Foundry::$subscriptionUserModel)::factory()->create();

        $subscription = $user->newSubscription('default', $plan->id);
        $subscription->saveAndInvoice([], true);

        $originalExpiresAt = $subscription->expires_at;

        // Act: Renew multiple times
        $subscription->renew(); // First renewal
        $firstRenewalExpiry = $subscription->expires_at;

        $subscription->renew(); // Second renewal
        $secondRenewalExpiry = $subscription->expires_at;

        // Assert: Each renewal should advance the period
        $this->assertNotEquals(
            $originalExpiresAt->format('Y-m-d'),
            $firstRenewalExpiry->format('Y-m-d'),
            'First renewal should change expires_at'
        );

        $this->assertNotEquals(
            $firstRenewalExpiry->format('Y-m-d'),
            $secondRenewalExpiry->format('Y-m-d'),
            'Second renewal should change expires_at again'
        );

        // Assert: Second renewal should be 2 months after original
        // Note: When dealing with sequential month additions, Carbon's overflow behavior means:
        // Jan 31 + 1 month = Feb 28/29, then Feb 28/29 + 1 month = Mar 28/29
        // So we need to calculate the expected date by doing sequential additions, not a single +2 months
        $expectedSecondExpiry = $originalExpiresAt->copy()->addMonth()->addMonth();
        $this->assertEquals(
            $expectedSecondExpiry->format('Y-m-d'),
            $secondRenewalExpiry->format('Y-m-d'),
            'After two renewals, expires_at should be 2 months after original (with sequential month addition)'
        );
    }
}
