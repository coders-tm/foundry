<?php

namespace Foundry\Exceptions;

use Exception;
use Foundry\Models\Subscription;

class SubscriptionUpdateFailure extends Exception
{
    /**
     * Create a new SubscriptionUpdateFailure instance.
     *
     * @return static
     */
    public static function incompleteSubscription(Subscription $subscription)
    {
        return new self(
            "The subscription \"{$subscription->plan_id}\" cannot be updated because its payment is incomplete."
        );
    }
}
