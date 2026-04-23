<?php

namespace Foundry\Actions\Subscription;

use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;
use Foundry\Services\Period;

class ResumeSubscription
{
    /**
     * Resume the canceled subscription.
     *
     * @throws \LogicException
     */
    public function execute(Subscription $subscription): Subscription
    {
        if (! $subscription->canceledOnGracePeriod()) {
            throw new \LogicException('Unable to resume subscription that is not within grace period.');
        }

        $subscription->guardAgainstIncomplete();

        // Reset the expires_at to the end of the billing period and clear canceled_at
        $period = new Period(
            $subscription->plan->interval->value,
            $subscription->plan->interval_count,
            $subscription->starts_at ?? Carbon::now()
        );

        // Finally, we will reset the expiration timestamp to the end of billing period
        // and clear the canceled timestamp to indicate that the subscription is active
        // again and is no longer "canceled". Then we shall save this record in the database.
        $subscription->fill([
            'status' => SubscriptionStatus::ACTIVE,
            'expires_at' => $period->getEndDate(),
            'canceled_at' => null,
        ])->save();

        return $subscription;
    }
}
