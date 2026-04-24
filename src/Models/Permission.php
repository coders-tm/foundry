<?php

namespace Foundry\Models;

use Foundry\Concerns\SerializeDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Permission extends Model
{
    use SerializeDate;

    protected $primaryKey = 'scope';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'module_key',
        'action',
        'scope',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_key', 'key');
    }

    public function permissionable()
    {
        return $this->morphTo();
    }

    protected static function booted()
    {
        static::saved(fn() => Cache::forget('foundry.permissions.all'));
        static::deleted(fn() => Cache::forget('foundry.permissions.all'));
    }
}
