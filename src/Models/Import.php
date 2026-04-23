<?php

namespace Foundry\Models;

use Foundry\Traits\SerializeDate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use HasUuids, SerializeDate;

    const STATUS_PENDING = 'Pending';

    const STATUS_PROCESSING = 'Processing';

    const STATUS_COMPLETED = 'Completed';

    const STATUS_FAILED = 'Failed';

    protected $fillable = [
        'model',
        'file_id',
        'user_id',
        'status',
        'options',
        'success',
        'failed',
        'skipped',
    ];

    protected $casts = [
        'options' => 'array',
        'success' => 'array',
        'failed' => 'array',
        'skipped' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function addLogs($type, $line): void
    {
        $lines = $this->{$type} ?? [];
        $this->update([
            $type => array_merge($lines, [$line]),
        ]);
    }

    public function getShortCodes(): array
    {
        return [
            'import' => [
                'model' => class_basename($this->model),
                'status' => $this->status,
                'successed' => count($this->success ?? []),
                'failed' => count($this->failed ?? []),
                'skipped' => count($this->skipped ?? []),
            ],
            'user' => $this->user ? $this->user->getShortCodes() : ['name' => 'System'],
        ];
    }

    protected static function booted()
    {
        parent::booted();
        static::creating(function ($model) {
            $model->status = static::STATUS_PENDING;
            $model->user_id = optional(user())->id;
        });
    }
}
