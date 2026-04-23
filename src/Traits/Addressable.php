<?php

namespace Foundry\Traits;

use Foundry\Models\Address;

trait Addressable
{
    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function updateOrCreateAddress(array $address)
    {
        if ($this->address) {
            $this->address->update((new Address($address))->toArray());
        } else {
            $this->address()->save(new Address($address));
        }

        return $this;
    }
}
