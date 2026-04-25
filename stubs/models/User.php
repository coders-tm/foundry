<?php

namespace App\Models;

use Foundry\Models\User as BaseUser;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends BaseUser implements MustVerifyEmail
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
        'is_active',
        // extra
        'title',
        'note',
        'status',
        'source',
        'gender',
        'rag',
    ];
}
