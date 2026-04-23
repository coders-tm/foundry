<?php

namespace Foundry\Traits;

use Foundry\Traits\Subscription\ManagesCustomer;
use Foundry\Traits\Subscription\ManagesSubscriptions;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscriptions;
}
