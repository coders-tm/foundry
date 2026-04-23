<?php

namespace Foundry\Exceptions\Plan;

use Exception;

class MaxFeatureUsedException extends Exception
{
    public function __construct()
    {
        parent::__construct('You have reached the maximum usage limit for this feature. Please upgrade your plan to access more or contact support for further assistance.');
    }
}
