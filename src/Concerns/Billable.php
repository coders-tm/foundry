<?php

namespace Foundry\Concerns;

use Foundry\Concerns\Subscription\ManagesCustomer;
use Foundry\Concerns\Subscription\ManagesSubscriptions;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscriptions;
}
