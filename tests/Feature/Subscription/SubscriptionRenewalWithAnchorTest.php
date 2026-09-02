<?php

use Foundry\Foundry;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {

    // Ensure anchor_from_invoice is enabled for these tests
    config(['foundry.subscription.anchor_from_invoice' => true]);
});

it('renewal extends period when anchor from invoice is enabled', function () {
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
    expect($subscription->starts_at->format('Y-m-d'))->not->toBe($originalStartsAt->format('Y-m-d'));

    expect($subscription->expires_at->format('Y-m-d'))->not->toBe($originalExpiresAt->format('Y-m-d'));

    // Assert: The new period should be approximately 1 month from original expiry
    $expectedNewStarts = $originalExpiresAt->copy();
    $expectedNewExpiry = $originalExpiresAt->copy()->addMonth();

    expect($subscription->starts_at->format('Y-m-d'))->toEqual($expectedNewStarts->format('Y-m-d'));

    expect($subscription->expires_at->format('Y-m-d'))->toEqual($expectedNewExpiry->format('Y-m-d'));
});

it('renewal works with quarterly plan and anchor enabled', function () {
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
    expect($subscription->expires_at->format('Y-m-d'))->toEqual($expectedNewExpiry->format('Y-m-d'));
});

it('multiple renewals properly advance period', function () {
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
    expect($originalExpiresAt->format('Y-m-d'))->not->toBe($firstRenewalExpiry->format('Y-m-d'));

    expect($firstRenewalExpiry->format('Y-m-d'))->not->toBe($secondRenewalExpiry->format('Y-m-d'));

    // Assert: Second renewal should be 2 months after original
    // Note: When dealing with sequential month additions, Carbon's overflow behavior means:
    // Jan 31 + 1 month = Feb 28/29, then Feb 28/29 + 1 month = Mar 28/29
    // So we need to calculate the expected date by doing sequential additions, not a single +2 months
    $expectedSecondExpiry = $originalExpiresAt->copy()->addMonth()->addMonth();
    expect($secondRenewalExpiry->format('Y-m-d'))->toEqual($expectedSecondExpiry->format('Y-m-d'));
});
