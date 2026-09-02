<?php

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Exceptions\SubscriptionUpdateFailure;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;

uses(TestCase::class);

it('we can check if a subscription is incomplete', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::INCOMPLETE,
    ]);

    expect($subscription->incomplete())->toBeTrue();
    expect($subscription->expired())->toBeFalse();
    expect($subscription->active())->toBeFalse();
});

it('we can check if a subscription is expired', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::EXPIRED,
    ]);

    expect($subscription->incomplete())->toBeFalse();
    expect($subscription->expired())->toBeTrue();
    expect($subscription->active())->toBeFalse();
});

it('we can check if a subscription is active', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    expect($subscription->incomplete())->toBeFalse();
    expect($subscription->expired())->toBeFalse();
    expect($subscription->active())->toBeTrue();
});

it('an incomplete subscription is not valid', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::INCOMPLETE,
    ]);

    expect($subscription->valid())->toBeFalse();
});

it('an expired subscription is not valid', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::EXPIRED,
    ]);

    expect($subscription->valid())->toBeFalse();
});

it('an active subscription is valid', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    expect($subscription->valid())->toBeTrue();
});

it('payment is incomplete when status is incomplete', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::INCOMPLETE,
    ]);

    expect($subscription->hasIncompletePayment())->toBeTrue();
});

it('payment is incomplete when status is expired', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::EXPIRED,
    ]);

    expect($subscription->hasIncompletePayment())->toBeTrue();
});

it('payment is not incomplete when status is active', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    expect($subscription->hasIncompletePayment())->toBeFalse();
});

it('incomplete subscriptions cannot be swapped', function () {
    $plans = Plan::factory(2)->create()->pluck('id')->toArray();
    $subscription = new Subscription([
        'status' => SubscriptionStatus::INCOMPLETE,
    ]);

    $subscription->setRelation('plan', Plan::find($plans[0]));

    expect(fn () => $subscription->swap($plans[1]))->toThrow(SubscriptionUpdateFailure::class);
});

it('extending a trial requires a date in the future', function () {
    expect(fn () => (new Subscription)->extendTrial(now()->subDay()))->toThrow(InvalidArgumentException::class);
});

it('it can determine if the subscription is on trial', function () {
    $subscription = new Subscription;
    $subscription->setDateFormat('Y-m-d H:i:s');
    $subscription->trial_ends_at = now()->addDay();

    expect($subscription->onTrial())->toBeTrue();

    $subscription = new Subscription;
    $subscription->setDateFormat('Y-m-d H:i:s');
    $subscription->trial_ends_at = now()->subDay();

    expect($subscription->onTrial())->toBeFalse();
});

it('it can determine if a trial has expired', function () {
    $subscription = new Subscription;
    $subscription->setDateFormat('Y-m-d H:i:s');
    $subscription->trial_ends_at = now()->subDay();

    expect($subscription->onTrialExpired())->toBeTrue();

    $subscription = new Subscription;
    $subscription->setDateFormat('Y-m-d H:i:s');
    $subscription->trial_ends_at = now()->addDay();

    expect($subscription->onTrialExpired())->toBeFalse();
});
