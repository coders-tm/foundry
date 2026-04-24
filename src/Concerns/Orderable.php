<?php

namespace Foundry\Concerns;

use Foundry\Foundry;

trait Orderable
{
    public function orders()
    {
        return $this->hasMany(Foundry::$orderModel, 'customer_id');
    }

    public function latestOrder()
    {
        return $this->hasOne(Foundry::$orderModel, 'customer_id')
            ->orderBy('created_at', 'desc');
    }
}
