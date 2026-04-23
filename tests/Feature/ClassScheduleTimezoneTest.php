<?php

namespace Foundry\Tests\Feature;

use Carbon\Carbon;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Workbench\App\Models\ClassSchedule;

/**
 * Tests that ClassSchedule's startAt() / endAt() and datetime fields
 * correctly honour the app-configured timezone:
 *
 *  - date_at   is stored as a plain calendar date (no timezone shift).
 *  - start_at / end_at are TIME strings entered in the app timezone.
 *  - startAt() / endAt() return ATOM-formatted strings with the correct
 *    app-timezone offset.
 *  - sign_off_at (a full datetime) is stored as UTC and serialized in app tz.
 */
class ClassScheduleTimezoneTest extends TestCase
{
    protected const APP_TIMEZONE = 'Asia/Kolkata'; // IST = UTC+5:30

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => self::APP_TIMEZONE]);
    }

    protected function tearDown(): void
    {
        config(['app.timezone' => 'UTC']);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // startAt()
    // -------------------------------------------------------------------------

    #[Test]
    public function start_at_returns_app_timezone_formatted_string(): void
    {
        $schedule = ClassSchedule::factory()->create([
            'date_at' => '2024-06-15',
            'start_at' => '09:00',
        ]);

        $startAt = $schedule->startAt();

        $this->assertNotNull($startAt);
        // The time string in IST must be 09:00
        $this->assertStringContainsString('09:00:00', $startAt,
            'startAt() should show the time as entered (IST 09:00)'
        );
        // The offset must match IST (+05:30)
        $this->assertStringContainsString('+05:30', $startAt,
            'startAt() should carry the IST timezone offset'
        );
    }

    #[Test]
    public function start_at_returns_null_when_date_at_is_null(): void
    {
        $schedule = ClassSchedule::factory()->make([
            'date_at' => null,
            'start_at' => '09:00',
        ]);

        $this->assertNull($schedule->startAt());
    }

    #[Test]
    public function start_at_returns_null_when_start_at_is_null(): void
    {
        $schedule = ClassSchedule::factory()->make([
            'date_at' => '2024-06-15',
            'start_at' => null,
        ]);

        $this->assertNull($schedule->startAt());
    }

    // -------------------------------------------------------------------------
    // endAt()
    // -------------------------------------------------------------------------

    #[Test]
    public function end_at_returns_app_timezone_formatted_string(): void
    {
        $schedule = ClassSchedule::factory()->create([
            'date_at' => '2024-06-15',
            'end_at' => '10:30',
        ]);

        $endAt = $schedule->endAt();

        $this->assertNotNull($endAt);
        $this->assertStringContainsString('10:30:00', $endAt,
            'endAt() should show the time as entered (IST 10:30)'
        );
        $this->assertStringContainsString('+05:30', $endAt);
    }

    #[Test]
    public function end_at_returns_null_when_end_at_is_null(): void
    {
        $schedule = ClassSchedule::factory()->make([
            'date_at' => '2024-06-15',
            'end_at' => null,
        ]);

        $this->assertNull($schedule->endAt());
    }

    // -------------------------------------------------------------------------
    // Duration
    // -------------------------------------------------------------------------

    #[Test]
    public function duration_is_calculated_in_minutes(): void
    {
        $schedule = ClassSchedule::factory()->make([
            'start_at' => '09:00',
            'end_at' => '10:30',
        ]);

        $this->assertEquals(90, $schedule->duration);
    }

    #[Test]
    public function duration_is_zero_when_times_are_null(): void
    {
        $schedule = ClassSchedule::factory()->make([
            'start_at' => null,
            'end_at' => null,
        ]);

        $this->assertEquals(0, $schedule->duration);
    }

    // -------------------------------------------------------------------------
    // date_at – no timezone shift (calendar date stays unchanged)
    // -------------------------------------------------------------------------

    #[Test]
    public function date_at_is_stored_as_calendar_date_without_timezone_shift(): void
    {
        $schedule = ClassSchedule::factory()->create([
            'date_at' => '2024-06-15',
        ]);

        // The raw DB value must be the original calendar date regardless of IST
        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'date_at' => '2024-06-15',
        ]);
    }

    #[Test]
    public function date_at_is_read_back_as_same_calendar_date(): void
    {
        $schedule = ClassSchedule::factory()->create([
            'date_at' => '2024-06-15',
        ]);

        $fresh = $schedule->fresh();

        $this->assertEquals('2024-06-15', $fresh->date_at->format('Y-m-d'));
    }

    // -------------------------------------------------------------------------
    // sign_off_at – full datetime stored as UTC, serialized in app timezone
    // -------------------------------------------------------------------------

    #[Test]
    public function sign_off_at_stores_app_timezone_input_as_utc(): void
    {
        // User submits IST 17:30 via the API (no timezone marker in the string)
        $schedule = ClassSchedule::factory()->create([
            'sign_off_at' => '2024-06-15 17:30:00',
        ]);

        // DB must hold UTC 12:00 (IST – 5:30)
        $this->assertDatabaseHas('class_schedules', [
            'id' => $schedule->id,
            'sign_off_at' => '2024-06-15 12:00:00',
        ]);
    }

    #[Test]
    public function sign_off_at_is_serialized_in_app_timezone(): void
    {
        // Store UTC noon directly (programmatic set via Carbon UTC instance)
        $schedule = ClassSchedule::factory()->create([
            'sign_off_at' => Carbon::create(2024, 6, 15, 12, 0, 0, 'UTC'),
        ]);

        $data = $schedule->fresh()->toArray();

        // Serialized output must be IST (17:30 +05:30)
        $this->assertStringContainsString('17:30:00', $data['sign_off_at']);
        $this->assertStringContainsString('+05:30', $data['sign_off_at']);
    }

    // -------------------------------------------------------------------------
    // startAt() / endAt() with UTC app timezone (no conversion)
    // -------------------------------------------------------------------------

    #[Test]
    public function start_at_uses_utc_offset_when_app_timezone_is_utc(): void
    {
        config(['app.timezone' => 'UTC']);

        $schedule = ClassSchedule::factory()->create([
            'date_at' => '2024-06-15',
            'start_at' => '09:00',
        ]);

        $startAt = $schedule->startAt();

        $this->assertStringContainsString('09:00:00', $startAt);
        $this->assertStringContainsString('+00:00', $startAt);
    }

    // -------------------------------------------------------------------------
    // Round-trip: set via app-tz string → read back → startAt() shows same time
    // -------------------------------------------------------------------------

    #[Test]
    public function round_trip_start_at_preserves_user_entered_time(): void
    {
        // User submits date 2024-06-15 with class starting at 09:00 IST
        $schedule = ClassSchedule::factory()->create([
            'date_at' => '2024-06-15',
            'start_at' => '09:00',
        ]);

        $fresh = ClassSchedule::find($schedule->id);

        // startAt() must report the same time the user entered (09:00 IST)
        $this->assertStringContainsString('09:00:00', $fresh->startAt());
        $this->assertStringContainsString('+05:30', $fresh->startAt());

        // The calendar date must not have drifted
        $this->assertEquals('2024-06-15', $fresh->date_at->format('Y-m-d'));
    }
}
