<?php

namespace Foundry\Concerns;

use Foundry\Concerns\Subscription\ManagesCustomer;
use Foundry\Concerns\Subscription\ManagesSubscriptions;
use Foundry\Mandate\Concerns\Biller;

trait Billable
{
    use Biller;
    use ManagesCustomer;
    use ManagesSubscriptions;
}
