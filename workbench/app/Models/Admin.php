<?php

namespace Workbench\App\Models;

use Foundry\Database\Factories\AdminFactory;
use Foundry\Models\Admin as Base;

class Admin extends Base
{
    protected $guarded = [];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return AdminFactory::new();
    }
}
