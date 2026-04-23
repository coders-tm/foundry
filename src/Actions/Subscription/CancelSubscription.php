<?php

namespace Foundry\Actions\Subscription;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;

class CancelSubscription
{
    /**
     * Cancel the subscription at the end of the billing period.
     */
    public function execute(Subscription $subscription): Subscription
    {
        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        if ($subscription->onTrial()) {
            $subscription->expires_at = $subscription->trial_ends_at;
        }

        $subscription->canceled_at = now();
        $subscription->save();

        return $subscription;
    }

    /**
     * Cancel the subscription at a specific moment in time.
     */
    public function cancelAt(Subscription $subscription, ?\DateTimeInterface $endsAt): Subscription
    {
        if ($endsAt instanceof \DateTimeInterface) {
            $subscription->expires_at = $endsAt->getTimestamp();
        }

        $subscription->status = SubscriptionStatus::CANCELED;
        $subscription->canceled_at = now();
        $subscription->save();

        return $subscription;
    }

    /**
     * Cancel the subscription immediately without invoicing.
     */
    public function cancelNow(Subscription $subscription): Subscription
    {
        $subscription->fill([
            'status' => SubscriptionStatus::CANCELED,
            'expires_at' => now(),
            'canceled_at' => now(),
        ])->save();

        return $subscription;
    }
}
