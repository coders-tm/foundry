<?php

namespace Foundry\Models;

use Foundry\Concerns\Fileable;
use Foundry\Concerns\SerializeDate;
use Foundry\Events\LogCreated;
use Foundry\Foundry;
use Foundry\Services\Logable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Log extends Model
{
    use Fileable, HasFactory, HasUuids, SerializeDate;

    const STATUS_ERROR = 'error';

    const STATUS_SUCCESS = 'success';

    const STATUS_WARNING = 'warning';

    protected $dispatchesEvents = [
        'created' => LogCreated::class,
    ];

    protected $fillable = [
        'type',
        'status',
        'message',
        'options',
        'admin_id',
    ];

    protected $hidden = [
        'logable_id',
    ];

    protected $appends = [
        'can_edit',
    ];

    protected $casts = [
        'options' => 'json',
    ];

    public function logable()
    {
        return $this->morphTo();
    }

    public function admin()
    {
        return $this->belongsTo(Foundry::$adminModel)->withOnly([]);
    }

    public function reply(): MorphMany
    {
        return $this->morphMany(static::class, 'logable');
    }

    /**
     * Resolve the log resource data (type, name, route) for this log entry.
     *
     * Mappers are registered per model class via Logable::add() in any service provider.
     *
     * @return array{type: string|null, name: string|null, route: string|null}
     */
    public function getResource(): array
    {
        if (! $this->relationLoaded('logable')) {
            $this->load('logable');
        }

        return Logable::resolve($this->logable);
    }

    public function getCanEditAttribute()
    {
        return $this->created_at->addMinutes(5)->gt(now());
    }

    public static function error($message, $context = []): void
    {
        \Illuminate\Support\Facades\Log::error($message, $context = []);
    }

    protected static function booted()
    {
        parent::booted();
        static::creating(function ($model) {
            if (empty($model->admin_id) && is_admin()) {
                $model->admin_id = user()->id ?? null;
            }
        });
    }
}
