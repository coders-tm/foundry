<?php

namespace App\Models;

use Foundry\Models\Admin as Model;

class Admin extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone_number',
        'is_super_admin',
        'is_active',
    ];
}
