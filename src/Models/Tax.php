<?php

namespace Foundry\Models;

use Foundry\Concerns\Logable;
use Foundry\Concerns\SerializeDate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory, HasUuids, Logable, SerializeDate;

    protected $fillable = [
        'country',
        'code',
        'state',
        'label',
        'compounded',
        'rate',
        'priority',
    ];

    protected $casts = [
        'compounded' => 'boolean',
    ];
}
