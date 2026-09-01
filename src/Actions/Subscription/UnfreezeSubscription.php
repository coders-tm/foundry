<?php

namespace Foundry\Actions\Subscription;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;

class UnfreezeSubscription
{
    /**
     * Unfreeze the subscription.
     *
     * @throws \LogicException
     */
    public function execute(Subscription $subscription): Subscription
    {
        if (! $subscription->onFreeze()) {
            throw new \LogicException('Subscription is not currently frozen.');
        }

        // Calculate freeze duration for logging
        $freezeDuration = $subscription->frozen_at->diffInDays(now());

        // Extend expires_at to compensate for the freeze period
        if ($subscription->expires_at) {
            $subscription->expires_at = $subscription->expires_at->addDays($freezeDuration);
        }

        // Reactivate subscription
        $subscription->fill([
            'status' => SubscriptionStatus::ACTIVE,
            'frozen_at' => null,
            'release_at' => null,
        ])->save();

        $subscription->logs()->create([
            'type' => 'unfreeze',
            'message' => "Subscription unfrozen after {$freezeDuration} days",
        ]);

        return $subscription;
    }
}
