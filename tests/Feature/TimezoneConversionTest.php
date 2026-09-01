<?php

use Carbon\Carbon;
use Foundry\Casts\AppTimezoneDate;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['app.timezone' => 'Asia/Kolkata']);

    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create(['interval' => 'month', 'interval_count' => 1]);
});

afterEach(function () {
    config(['app.timezone' => 'UTC']);
});

it('serialize date converts utc carbon to app timezone', function () {
    $utcTime = Carbon::create(2024, 6, 15, 12, 0, 0, 'UTC');

    $subscription = Subscription::factory()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'starts_at' => $utcTime,
    ]);

    $data = $subscription->toArray();

    $this->assertStringContainsString('17:30:00', $data['starts_at'],
        'Serialized date should be in app timezone (IST 17:30), not UTC 12:00'
    );
    $this->assertStringContainsString('+05:30', $data['starts_at'],
        'Serialized date should carry the IST offset'
    );
});

it('serialize date keeps utc when app timezone is utc', function () {
    config(['app.timezone' => 'UTC']);

    $utcTime = Carbon::create(2024, 6, 15, 12, 0, 0, 'UTC');

    $subscription = Subscription::factory()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'starts_at' => $utcTime,
    ]);

    $data = $subscription->toArray();

    $this->assertStringContainsString('12:00:00', $data['starts_at']);
    $this->assertStringContainsString('+00:00', $data['starts_at']);
});

it('setting datetime string without timezone stores as utc', function () {
    $subscription = Subscription::factory()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'starts_at' => '2024-06-15 17:30:00',
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'starts_at' => '2024-06-15 12:00:00',
    ]);
});

it('setting carbon utc instance stores as utc', function () {
    $utcCarbon = Carbon::create(2024, 6, 15, 12, 0, 0, 'UTC');

    $subscription = Subscription::factory()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'starts_at' => $utcCarbon,
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'starts_at' => '2024-06-15 12:00:00',
    ]);
});

it('setting carbon ist instance stores equivalent utc', function () {
    $istCarbon = Carbon::create(2024, 6, 15, 12, 0, 0, 'Asia/Kolkata');

    $subscription = Subscription::factory()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'starts_at' => $istCarbon,
    ]);

    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'starts_at' => '2024-06-15 06:30:00',
    ]);
});

it('round trip preserves absolute moment', function () {
    $subscription = Subscription::factory()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
        'starts_at' => '2024-06-15 17:30:00',
    ]);

    $fresh = $subscription->fresh();

    $serialized = $fresh->toArray()['starts_at'];
    $this->assertStringContainsString('17:30:00', $serialized);
    $this->assertStringContainsString('+05:30', $serialized);

    $this->assertDatabaseHas('subscriptions', [
        'id' => $subscription->id,
        'starts_at' => '2024-06-15 12:00:00',
    ]);
});

it('app timezone date cast converts input to utc', function () {
    $cast = new AppTimezoneDate;

    $this->assertEquals(
        '2024-06-15 12:00:00',
        $cast->set(null, 'field', '2024-06-15 17:30:00', [])
    );
});

it('app timezone date cast returns carbon in app timezone', function () {
    $cast = new AppTimezoneDate;

    $carbon = $cast->get(null, 'field', '2024-06-15 12:00:00', []);

    $this->assertInstanceOf(Carbon::class, $carbon);
    $this->assertEquals('17:30:00', $carbon->format('H:i:s'));
    $this->assertEquals('+05:30', $carbon->format('P'));
});
