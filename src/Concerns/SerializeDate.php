<?php

namespace Foundry\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Foundry\Foundry;

trait SerializeDate
{
    /**
     * Prepare a DateTime instance for array/JSON serialization.
     *
     * Carbon instances in the model are always UTC (DB stores UTC).
     * This converts to the app-configured display timezone before formatting.
     *
     * @param  DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate($date)
    {
        if ($date instanceof DateTimeInterface) {
            return Carbon::instance($date)
                ->setTimezone(config('app.timezone', 'UTC'))
                ->format(Foundry::$dateTimeFormat);
        }

        return $date;
    }

    /**
     * Convert a DateTime to a storable UTC string.
     *
     * - DateTimeInterface instances: converted to UTC using their absolute moment.
     * - Numeric (Unix timestamp): always UTC-based.
     * - Plain strings (no timezone info): interpreted as the app timezone, then
     *   converted to UTC.  This handles frontend input where the user submits
     *   a local datetime (e.g. "2024-06-15 17:30:00" in IST) and the value
     *   must be stored as UTC ("2024-06-15 12:00:00") in the database.
     *
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        if (empty($value)) {
            return $value;
        }

        // DateTimeInterface (including Carbon) already carries an explicit timezone.
        // Convert its absolute moment to a UTC string for storage.
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc()->format($this->getDateFormat());
        }

        // Unix timestamp is always UTC-based.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value, 'UTC')->format($this->getDateFormat());
        }

        // Date-only string (YYYY-MM-DD, no time component): a calendar date is
        // timezone-agnostic — do not shift it to UTC.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return $value;
        }

        // Full datetime string (no timezone info): treat as app timezone so that
        // frontend-submitted values are correctly converted to UTC.
        return Carbon::parse($value, config('app.timezone', 'UTC'))
            ->utc()
            ->format($this->getDateFormat());
    }
}
