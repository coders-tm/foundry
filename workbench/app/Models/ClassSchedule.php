<?php

namespace Workbench\App\Models;

use Carbon\Carbon;
use Foundry\Database\Factories\ClassScheduleFactory;
use Foundry\Foundry;
use Foundry\Traits\Core;
use Illuminate\Database\Eloquent\Model;

/**
 * A minimal ClassSchedule model used in workbench / tests.
 *
 * Key timezone behaviour:
 *  - date_at   → stored as a plain DATE (calendar date, no timezone shift).
 *  - start_at  → stored as a TIME string (HH:MM) entered in the app timezone.
 *  - end_at    → stored as a TIME string (HH:MM) entered in the app timezone.
 *  - sign_off_at → full datetime, goes through fromDateTime() → stored as UTC.
 *  - startAt() / endAt() combine date_at + time string and format in app timezone.
 */
class ClassSchedule extends Model
{
    use Core;

    protected $table = 'class_schedules';

    protected $fillable = [
        'title',
        'date_at',
        'start_at',
        'end_at',
        'sign_off_at',
        'is_active',
    ];

    protected $casts = [
        'date_at' => 'date:Y-m-d',
        'sign_off_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Return the class start moment as a formatted string in the app timezone.
     *
     * date_at holds a calendar date; start_at holds a "HH:MM" time string that
     * was entered by the user in the app timezone.  We therefore parse the
     * combined value in the app timezone before formatting.
     */
    public function startAt(): ?string
    {
        if (! $this->date_at || ! $this->start_at) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $this->date_at->format('Y-m-d').' '.substr($this->start_at, 0, 5),
            config('app.timezone', 'UTC')
        )->format(Foundry::$dateTimeFormat);
    }

    /**
     * Return the class end moment as a formatted string in the app timezone.
     */
    public function endAt(): ?string
    {
        if (! $this->date_at || ! $this->end_at) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $this->date_at->format('Y-m-d').' '.substr($this->end_at, 0, 5),
            config('app.timezone', 'UTC')
        )->format(Foundry::$dateTimeFormat);
    }

    /**
     * Duration in minutes between start_at and end_at.
     */
    public function getDurationAttribute(): int
    {
        if ($this->start_at && $this->end_at) {
            return Carbon::createFromFormat('H:i', substr($this->start_at, 0, 5))
                ->diffInMinutes(Carbon::createFromFormat('H:i', substr($this->end_at, 0, 5)));
        }

        return 0;
    }

    protected static function newFactory(): ClassScheduleFactory
    {
        return ClassScheduleFactory::new();
    }
}
