<?php

namespace Foundry\Exceptions;

use Exception;

class IntegrityException extends Exception
{
    protected $code = 403;
}
