<?php

namespace Foundry\Models\Order;

use Foundry\Models\User as ShopCustomer;

class Customer extends ShopCustomer
{
    protected $table = 'users';

    protected $appends = [
        'name',
    ];

    protected $with = [
        //
    ];
}
