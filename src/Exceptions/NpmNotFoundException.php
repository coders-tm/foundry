<?php

namespace Foundry\Exceptions;

use Exception;

class NpmNotFoundException extends Exception
{
    public function __construct($message = null)
    {
        parent::__construct($message ?? 'Npm is not installed on the server. Please install npm and try again.');
    }
}
