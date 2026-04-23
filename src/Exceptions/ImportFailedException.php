<?php

namespace Foundry\Exceptions;

use Exception;

class ImportFailedException extends Exception
{
    public function __construct($message = null)
    {
        parent::__construct($message ?? 'Record with the same email already exists.');
    }
}
