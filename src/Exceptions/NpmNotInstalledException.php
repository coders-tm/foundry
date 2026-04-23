<?php

namespace Foundry\Exceptions;

use Exception;

class NpmNotInstalledException extends Exception
{
    public function __construct($message = null)
    {
        parent::__construct($message ?? 'Npm is installed, but the test command failed. Make sure to run "npm install" in the project root.');
    }
}
