<?php

namespace Foundry\Models;

use Foundry\Database\Factories\AddressFactory;
use Foundry\Concerns\Core;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use Core;

    protected $fillable = [
        'first_name',
        'last_name',
        'company',
        'phone_number',
        'line1',
        'line2',
        'city',
        'state',
        'state_code',
        'postal_code',
        'country',
        'country_code',
        'default',
        'ref',
    ];

    protected $casts = [
        'default' => 'boolean',
    ];

    protected $hidden = [
        'addressable_type',
        'addressable_id',
    ];

    public function addressable()
    {
        return $this->morphTo();
    }

    public function getLabelAttribute()
    {
        return implode(', ', collect($this->attributes)->filter()->only([
            'line1',
            'line2',
            'city',
            'state',
            'country',
            'postal_code',
        ])->toArray());
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return AddressFactory::new();
    }
}
