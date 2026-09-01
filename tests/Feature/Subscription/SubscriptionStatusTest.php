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

    $this->assertTrue($subscription->incomplete());
    $this->assertFalse($subscription->expired());
    $this->assertFalse($subscription->active());
});

it('we can check if a subscription is expired', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::EXPIRED,
    ]);

    $this->assertFalse($subscription->incomplete());
    $this->assertTrue($subscription->expired());
    $this->assertFalse($subscription->active());
});

it('we can check if a subscription is active', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $this->assertFalse($subscription->incomplete());
    $this->assertFalse($subscription->expired());
    $this->assertTrue($subscription->active());
});

it('an incomplete subscription is not valid', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::INCOMPLETE,
    ]);

    $this->assertFalse($subscription->valid());
});

it('an expired subscription is not valid', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::EXPIRED,
    ]);

    $this->assertFalse($subscription->valid());
});

it('an active subscription is valid', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $this->assertTrue($subscription->valid());
});

it('payment is incomplete when status is incomplete', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::INCOMPLETE,
    ]);

    $this->assertTrue($subscription->hasIncompletePayment());
});

it('payment is incomplete when status is expired', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::EXPIRED,
    ]);

    $this->assertTrue($subscription->hasIncompletePayment());
});

it('payment is not incomplete when status is active', function () {
    $subscription = new Subscription([
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    $this->assertFalse($subscription->hasIncompletePayment());
});

it('incomplete subscriptions cannot be swapped', function () {
    $plans = Plan::factory(2)->create()->pluck('id')->toArray();
    $subscription = new Subscription([
        'status' => SubscriptionStatus::INCOMPLETE,
    ]);

    $subscription->setRelation('plan', Plan::find($plans[0]));

    $this->expectException(SubscriptionUpdateFailure::class);

    $subscription->swap($plans[1]);
});

it('extending a trial requires a date in the future', function () {
    $this->expectException(InvalidArgumentException::class);

    (new Subscription)->extendTrial(now()->subDay());
});

it('it can determine if the subscription is on trial', function () {
    $subscription = new Subscription;
    $subscription->setDateFormat('Y-m-d H:i:s');
    $subscription->trial_ends_at = now()->addDay();

    $this->assertTrue($subscription->onTrial());

    $subscription = new Subscription;
    $subscription->setDateFormat('Y-m-d H:i:s');
    $subscription->trial_ends_at = now()->subDay();

    $this->assertFalse($subscription->onTrial());
});

it('it can determine if a trial has expired', function () {
    $subscription = new Subscription;
    $subscription->setDateFormat('Y-m-d H:i:s');
    $subscription->trial_ends_at = now()->subDay();

    $this->assertTrue($subscription->onTrialExpired());

    $subscription = new Subscription;
    $subscription->setDateFormat('Y-m-d H:i:s');
    $subscription->trial_ends_at = now()->addDay();

    $this->assertFalse($subscription->onTrialExpired());
});
