<?php

namespace Foundry\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

trait Core
{
    use CoreBase, SoftDeletes;
}
