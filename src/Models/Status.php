<?php

namespace Foundry\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'label',
    ];

    protected $hidden = [
        'statusable_type',
        'statusable_id',
    ];

    public function statusable()
    {
        return $this->morphTo();
    }
}
