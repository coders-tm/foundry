<?php

namespace Foundry\Tests\Feature\Subscription;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Exceptions\SubscriptionUpdateFailure;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Tests\TestCase;
use InvalidArgumentException;

class SubscriptionStatusTest extends TestCase
{
    public function test_we_can_check_if_a_subscription_is_incomplete()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::INCOMPLETE,
        ]);

        $this->assertTrue($subscription->incomplete());
        $this->assertFalse($subscription->expired());
        $this->assertFalse($subscription->active());
    }

    public function test_we_can_check_if_a_subscription_is_expired()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::EXPIRED,
        ]);

        $this->assertFalse($subscription->incomplete());
        $this->assertTrue($subscription->expired());
        $this->assertFalse($subscription->active());
    }

    public function test_we_can_check_if_a_subscription_is_active()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        $this->assertFalse($subscription->incomplete());
        $this->assertFalse($subscription->expired());
        $this->assertTrue($subscription->active());
    }

    public function test_an_incomplete_subscription_is_not_valid()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::INCOMPLETE,
        ]);

        $this->assertFalse($subscription->valid());
    }

    public function test_an_expired_subscription_is_not_valid()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::EXPIRED,
        ]);

        $this->assertFalse($subscription->valid());
    }

    public function test_an_active_subscription_is_valid()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        $this->assertTrue($subscription->valid());
    }

    public function test_payment_is_incomplete_when_status_is_incomplete()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::INCOMPLETE,
        ]);

        $this->assertTrue($subscription->hasIncompletePayment());
    }

    public function test_payment_is_incomplete_when_status_is_expired()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::EXPIRED,
        ]);

        $this->assertTrue($subscription->hasIncompletePayment());
    }

    public function test_payment_is_not_incomplete_when_status_is_active()
    {
        $subscription = new Subscription([
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        $this->assertFalse($subscription->hasIncompletePayment());
    }

    public function test_incomplete_subscriptions_cannot_be_swapped()
    {
        $plans = Plan::factory(2)->create()->pluck('id')->toArray();
        $subscription = new Subscription([
            'status' => SubscriptionStatus::INCOMPLETE,
        ]);

        $subscription->setRelation('plan', Plan::find($plans[0]));

        $this->expectException(SubscriptionUpdateFailure::class);

        $subscription->swap($plans[1]);
    }

    public function test_extending_a_trial_requires_a_date_in_the_future()
    {
        $this->expectException(InvalidArgumentException::class);

        (new Subscription)->extendTrial(now()->subDay());
    }

    public function test_it_can_determine_if_the_subscription_is_on_trial()
    {
        $subscription = new Subscription;
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->trial_ends_at = now()->addDay();

        $this->assertTrue($subscription->onTrial());

        $subscription = new Subscription;
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->trial_ends_at = now()->subDay();

        $this->assertFalse($subscription->onTrial());
    }

    public function test_it_can_determine_if_a_trial_has_expired()
    {
        $subscription = new Subscription;
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->trial_ends_at = now()->subDay();

        $this->assertTrue($subscription->onTrialExpired());

        $subscription = new Subscription;
        $subscription->setDateFormat('Y-m-d H:i:s');
        $subscription->trial_ends_at = now()->addDay();

        $this->assertFalse($subscription->onTrialExpired());
    }
}
