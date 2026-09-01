<?php

namespace Foundry\Actions\Subscription;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;

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

        $subscription->fill([
            'status' => SubscriptionStatus::ACTIVE,
            'cancels_at' => null,
        ])->save();

        return $subscription;
    }
}
