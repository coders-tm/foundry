<?php

namespace Foundry\Models\Order;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasUuids;

    protected $table = 'order_contacts';

    public $timestamps = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
    ];

    protected $hidden = [
        'contactable_type',
        'contactable_id',
    ];

    public function contactable()
    {
        return $this->morphTo();
    }
}
