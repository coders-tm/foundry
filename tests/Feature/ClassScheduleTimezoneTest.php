<?php

use Carbon\Carbon;
use Foundry\Tests\TestCase;
use Workbench\App\Models\ClassSchedule;

uses(TestCase::class);

beforeEach(function () {
    config(['app.timezone' => 'Asia/Kolkata']);
});

afterEach(function () {
    config(['app.timezone' => 'UTC']);
});

it('returns app timezone formatted string for startAt', function () {
    $schedule = ClassSchedule::factory()->create([
        'date_at' => '2024-06-15',
        'start_at' => '09:00',
    ]);

    $startAt = $schedule->startAt();

    $this->assertNotNull($startAt);
    $this->assertStringContainsString('09:00:00', $startAt,
        'startAt() should show the time as entered (IST 09:00)'
    );
    $this->assertStringContainsString('+05:30', $startAt,
        'startAt() should carry the IST timezone offset'
    );
});

it('returns null when date_at is null for startAt', function () {
    $schedule = ClassSchedule::factory()->make([
        'date_at' => null,
        'start_at' => '09:00',
    ]);

    $this->assertNull($schedule->startAt());
});

it('returns null when start_at is null', function () {
    $schedule = ClassSchedule::factory()->make([
        'date_at' => '2024-06-15',
        'start_at' => null,
    ]);

    $this->assertNull($schedule->startAt());
});

it('returns app timezone formatted string for endAt', function () {
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
});

it('returns null when end_at is null', function () {
    $schedule = ClassSchedule::factory()->make([
        'date_at' => '2024-06-15',
        'end_at' => null,
    ]);

    $this->assertNull($schedule->endAt());
});

it('calculates duration in minutes', function () {
    $schedule = ClassSchedule::factory()->make([
        'start_at' => '09:00',
        'end_at' => '10:30',
    ]);

    $this->assertEquals(90, $schedule->duration);
});

it('has zero duration when times are null', function () {
    $schedule = ClassSchedule::factory()->make([
        'start_at' => null,
        'end_at' => null,
    ]);

    $this->assertEquals(0, $schedule->duration);
});

it('stores date_at as calendar date without timezone shift', function () {
    $schedule = ClassSchedule::factory()->create([
        'date_at' => '2024-06-15',
    ]);

    $this->assertDatabaseHas('class_schedules', [
        'id' => $schedule->id,
        'date_at' => '2024-06-15',
    ]);
});

it('reads date_at back as same calendar date', function () {
    $schedule = ClassSchedule::factory()->create([
        'date_at' => '2024-06-15',
    ]);

    $fresh = $schedule->fresh();

    $this->assertEquals('2024-06-15', $fresh->date_at->format('Y-m-d'));
});

it('stores sign_off_at app timezone input as utc', function () {
    $schedule = ClassSchedule::factory()->create([
        'sign_off_at' => '2024-06-15 17:30:00',
    ]);

    $this->assertDatabaseHas('class_schedules', [
        'id' => $schedule->id,
        'sign_off_at' => '2024-06-15 12:00:00',
    ]);
});

it('serializes sign_off_at in app timezone', function () {
    $schedule = ClassSchedule::factory()->create([
        'sign_off_at' => Carbon::create(2024, 6, 15, 12, 0, 0, 'UTC'),
    ]);

    $data = $schedule->fresh()->toArray();

    $this->assertStringContainsString('17:30:00', $data['sign_off_at']);
    $this->assertStringContainsString('+05:30', $data['sign_off_at']);
});

it('uses utc offset for start_at when app timezone is utc', function () {
    config(['app.timezone' => 'UTC']);

    $schedule = ClassSchedule::factory()->create([
        'date_at' => '2024-06-15',
        'start_at' => '09:00',
    ]);

    $startAt = $schedule->startAt();

    $this->assertStringContainsString('09:00:00', $startAt);
    $this->assertStringContainsString('+00:00', $startAt);
});

it('preserves user entered time in round trip start_at', function () {
    $schedule = ClassSchedule::factory()->create([
        'date_at' => '2024-06-15',
        'start_at' => '09:00',
    ]);

    $fresh = ClassSchedule::find($schedule->id);

    $this->assertStringContainsString('09:00:00', $fresh->startAt());
    $this->assertStringContainsString('+05:30', $fresh->startAt());

    $this->assertEquals('2024-06-15', $fresh->date_at->format('Y-m-d'));
});
