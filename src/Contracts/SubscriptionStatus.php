<?php

namespace Foundry\Contracts;

interface SubscriptionStatus
{
    const ACTIVE = 'active';

    const CANCELED = 'canceled';

    const EXPIRED = 'expired';

    const INCOMPLETE = 'incomplete';

    const PAUSED = 'paused';

    const TRIALING = 'trialing';

    const PENDING = 'pending';
}
