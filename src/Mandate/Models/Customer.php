<?php

namespace Foundry\Mandate\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer model for storing user provider-specific customer references.
 */
class Customer extends Model
{
    use HasUuids;

    protected $table = 'payment_provider_customers';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'options',
    ];

    protected $casts = [
        'options' => 'json',
    ];
}
