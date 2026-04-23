<?php

namespace Foundry\Contracts;

interface Currencyable
{
    /**
     * Get the list of currency fields to be converted.
     *
     * @return array Field names that contain currency amounts
     */
    public function getCurrencyFields(): array;
}
