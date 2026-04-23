<?php

namespace Foundry\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent cast for datetime fields that accept user input in the app-configured timezone.
 *
 * - GET: reads the UTC value from the database and returns a Carbon instance
 *        converted to the app timezone (config('app.timezone')).
 * - SET: accepts a datetime string/Carbon instance in the app timezone,
 *        converts to UTC, and stores the UTC string so the database always
 *        holds UTC values.
 *
 * Usage in model $casts:
 *   protected $casts = [
 *       'starts_at'  => AppTimezoneDate::class,
 *       'expires_at' => AppTimezoneDate::class,
 *   ];
 */
class AppTimezoneDate implements CastsAttributes
{
    /**
     * Cast the stored UTC value to a Carbon instance in the app timezone.
     *
     * @param  Model  $model
     * @param  mixed  $value
     */
    public function get($model, string $key, $value, array $attributes): ?Carbon
    {
        if (is_null($value)) {
            return null;
        }

        return Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * Convert the app-timezone datetime input to a UTC string for database storage.
     *
     * @param  Model  $model
     * @param  mixed  $value
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $appTimezone = config('app.timezone', 'UTC');

        return Carbon::parse($value, $appTimezone)->utc()->format('Y-m-d H:i:s');
    }
}
