<?php

namespace Foundry\Services;

use Foundry\Contracts\StateInterface;

class StateLoader implements StateInterface
{
    /**
     * {@inheritdoc}
     */
    public function isStable(): bool
    {
        return app()->environment('local') || app()->environment('testing') || app()->runningUnitTests();
    }
}
