<?php

namespace Foundry\Services;

use Foundry\Contracts\SubscriptionGateway;
use Foundry\Models\Subscription;
use Foundry\Services\Gateways\CommonSubscriptionGateway;
use Foundry\Services\Gateways\GoCardlessSubscriptionGateway;

class GatewaySubscriptionFactory
{
    /**
     * Create a subscription gateway for the given subscription
     *
     * @throws \Exception
     */
    public static function make(Subscription $subscription): SubscriptionGateway
    {
        $provider = $subscription->provider;

        switch ($provider) {
            case 'gocardless':
                return new GoCardlessSubscriptionGateway($subscription);
            default:
                return new CommonSubscriptionGateway($subscription);
        }
    }
}
