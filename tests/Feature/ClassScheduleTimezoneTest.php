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

    expect($startAt)->not->toBeNull();
    expect($startAt)->toContain('09:00:00');
    expect($startAt)->toContain('+05:30');
});

it('returns null when date_at is null for startAt', function () {
    $schedule = ClassSchedule::factory()->make([
        'date_at' => null,
        'start_at' => '09:00',
    ]);

    expect($schedule->startAt())->toBeNull();
});

it('returns null when start_at is null', function () {
    $schedule = ClassSchedule::factory()->make([
        'date_at' => '2024-06-15',
        'start_at' => null,
    ]);

    expect($schedule->startAt())->toBeNull();
});

it('returns app timezone formatted string for endAt', function () {
    $schedule = ClassSchedule::factory()->create([
        'date_at' => '2024-06-15',
        'end_at' => '10:30',
    ]);

    $endAt = $schedule->endAt();

    expect($endAt)->not->toBeNull();
    expect($endAt)->toContain('10:30:00');
    expect($endAt)->toContain('+05:30');
});

it('returns null when end_at is null', function () {
    $schedule = ClassSchedule::factory()->make([
        'date_at' => '2024-06-15',
        'end_at' => null,
    ]);

    expect($schedule->endAt())->toBeNull();
});

it('calculates duration in minutes', function () {
    $schedule = ClassSchedule::factory()->make([
        'start_at' => '09:00',
        'end_at' => '10:30',
    ]);

    expect($schedule->duration)->toEqual(90);
});

it('has zero duration when times are null', function () {
    $schedule = ClassSchedule::factory()->make([
        'start_at' => null,
        'end_at' => null,
    ]);

    expect($schedule->duration)->toEqual(0);
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

    expect($fresh->date_at->format('Y-m-d'))->toBe('2024-06-15');
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

    expect($data['sign_off_at'])->toContain('17:30:00');
    expect($data['sign_off_at'])->toContain('+05:30');
});

it('uses utc offset for start_at when app timezone is utc', function () {
    config(['app.timezone' => 'UTC']);

    $schedule = ClassSchedule::factory()->create([
        'date_at' => '2024-06-15',
        'start_at' => '09:00',
    ]);

    $startAt = $schedule->startAt();

    expect($startAt)->toContain('09:00:00');
    expect($startAt)->toContain('+00:00');
});

it('preserves user entered time in round trip start_at', function () {
    $schedule = ClassSchedule::factory()->create([
        'date_at' => '2024-06-15',
        'start_at' => '09:00',
    ]);

    $fresh = ClassSchedule::find($schedule->id);

    expect($fresh->startAt())->toContain('09:00:00');
    expect($fresh->startAt())->toContain('+05:30');

    expect($fresh->date_at->format('Y-m-d'))->toBe('2024-06-15');
});
