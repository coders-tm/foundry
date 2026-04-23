<?php

namespace Foundry\Contracts;

interface StateInterface
{
    /**
     * Check if the application state is stable.
     */
    public function isStable(): bool;
}
