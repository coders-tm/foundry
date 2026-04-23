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

        // Extend contract end date if this is a contract subscription
        if ($subscription->isContract() && $subscription->total_cycles) {
            $this->extendContractForFreeze($subscription, $freezeDuration);
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

    /**
     * Extend contract end date to compensate for freeze period.
     *
     * @param  int  $freezeDays  Number of days the subscription was frozen
     */
    protected function extendContractForFreeze(Subscription $subscription, int $freezeDays): void
    {
        if (! $subscription->expires_at) {
            return;
        }

        // Extend expires_at by the freeze duration
        $subscription->expires_at = $subscription->expires_at->addDays($freezeDays);

        // Note: We don't save here as the calling method will save
    }
}
