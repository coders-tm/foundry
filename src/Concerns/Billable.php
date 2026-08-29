<?php

namespace Foundry\Concerns;

use Foundry\Billable\Concerns\Biller;
use Foundry\Concerns\Subscription\ManagesCustomer;
use Foundry\Concerns\Subscription\ManagesSubscriptions;

trait Billable
{
    use ManagesCustomer;
    use ManagesSubscriptions;
    use Biller;
}
