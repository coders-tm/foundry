<?php

namespace Foundry\Traits;

use Foundry\Enum\LogType;
use Foundry\Models\Log;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

trait Logable
{
    public function logs(): MorphMany
    {
        return $this->morphMany(Log::class, 'logable');
    }

    protected static function getLogName($model)
    {
        if ($model->logName) {
            return $model->logName;
        }

        return Str::of(class_basename(get_class($model)))->snake()->replace('_', ' ')->title();
    }

    protected static function getLogValue(string $key, $value)
    {
        return static::mapLogValue($key, $value);
    }

    protected static function mapLogValue(string $key, $value)
    {
        return is_array($value) ? implode(', ', array_filter($value)) : $value;
    }

    public function getLoggable()
    {
        return array_diff($this->fillable, $this->logIgnore ?? []);
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($model) {
            $modelName = static::getLogName($model);
            $data = [
                'message' => "{$modelName} has been created.",
            ];
            if (! empty($model->log_options)) {
                $data['options'] = $model->log_options;
            }
            $model->logs()->updateOrCreate([
                'type' => LogType::CREATED,
            ], $data);
        });
        static::updated(function ($model) {
            $modelName = static::getLogName($model);
            $options = [];
            foreach ($model->getLoggable() as $key) {
                if ($model->wasChanged($key)) {
                    $previous = $model->getOriginal($key);
                    $current = $model[$key];
                    $options[$key] = [
                        '_previous' => $previous,
                        'previous' => static::getLogValue($key, $previous),
                        '_current' => $current,
                        'current' => static::getLogValue($key, $current),
                    ];

                    $method = 'on'.Str::studly(str_replace('.', '_', $key)).'Updated';

                    if (method_exists(static::class, $method)) {
                        static::{$method}($model, $options[$key]);
                    }
                }
            }

            if (! empty($options)) {
                $model->logs()->create([
                    'type' => LogType::UPDATED,
                    'message' => "{$modelName} has been updated.",
                    'options' => $options,
                ]);
            }
        });
        static::deleted(function ($model) {
            $modelName = static::getLogName($model);
            $model->logs()->create([
                'type' => LogType::DELETED,
                'message' => "{$modelName} has been deleted.",
            ]);
        });
        if (method_exists(static::class, 'forceDeleted')) {
            static::forceDeleted(function ($model) {
                $modelName = static::getLogName($model);
                $model->logs()->create([
                    'type' => LogType::PERMANENTLY_DELETED,
                    'message' => "{$modelName} has been permanently deleted.",
                ]);
            });
        }
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                $modelName = static::getLogName($model);
                $model->logs()->create([
                    'type' => LogType::RESTORED,
                    'message' => "{$modelName} has been restored.",
                ]);
            });
        }
    }
}
