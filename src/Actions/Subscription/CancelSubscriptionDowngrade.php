<?php

namespace Foundry\Actions\Subscription;

use Foundry\Models\Subscription;

class CancelSubscriptionDowngrade
{
    /**
     * Cancel the downgrade plan.
     */
    public function execute(Subscription $subscription): Subscription
    {
        if (! $subscription->hasNexPlan()) {
            return $subscription;
        }

        $subscription->update([
            'next_plan' => null,
            'is_downgrade' => false,
        ]);

        return $subscription;
    }
}
