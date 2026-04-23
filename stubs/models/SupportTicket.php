<?php

namespace App\Models;

use Foundry\Models\SupportTicket as Model;

class SupportTicket extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'seen',
        'is_archived',
        'user_archived',
        'order_id',
        'source',
    ];
}
