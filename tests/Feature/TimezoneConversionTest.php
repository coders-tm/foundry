<?php

namespace Foundry\Tests\Feature;

use Carbon\Carbon;
use Foundry\Casts\AppTimezoneDate;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests that datetime values are:
 *  - Stored in UTC in the database regardless of app timezone.
 *  - Serialized (JSON/array) in the app-configured timezone.
 *  - Accepted from the frontend as app-timezone strings and stored as UTC
 *    automatically (no per-field cast required).
 */
class TimezoneConversionTest extends TestCase
{
    // IST is UTC+5:30
    protected const APP_TIMEZONE = 'Asia/Kolkata';

    protected User $user;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => self::APP_TIMEZONE]);

        $this->user = User::factory()->create();
        $this->plan = Plan::factory()->create(['interval' => 'month', 'interval_count' => 1]);
    }

    protected function tearDown(): void
    {
        config(['app.timezone' => 'UTC']);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // serializeDate – UTC Carbon → app timezone in JSON output
    // -------------------------------------------------------------------------

    #[Test]
    public function serialize_date_converts_utc_carbon_to_app_timezone(): void
    {
        // noon UTC → 17:30 IST
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
    }

    #[Test]
    public function serialize_date_keeps_utc_when_app_timezone_is_utc(): void
    {
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
    }

    // -------------------------------------------------------------------------
    // fromDateTime – app-timezone string input → UTC stored in DB
    // -------------------------------------------------------------------------

    #[Test]
    public function setting_datetime_string_without_timezone_stores_as_utc(): void
    {
        // Frontend sends "2024-06-15 17:30:00" (IST, no offset marker).
        // fromDateTime() must interpret it as IST and store UTC 12:00:00.
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'starts_at' => '2024-06-15 17:30:00',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'starts_at' => '2024-06-15 12:00:00',
        ]);
    }

    #[Test]
    public function setting_carbon_utc_instance_stores_as_utc(): void
    {
        // Programmatic code passes an explicit UTC Carbon; must not be re-converted.
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
    }

    #[Test]
    public function setting_carbon_ist_instance_stores_equivalent_utc(): void
    {
        // Carbon instance created in IST timezone.
        // fromDateTime() must use its absolute moment → UTC 06:30.
        $istCarbon = Carbon::create(2024, 6, 15, 12, 0, 0, 'Asia/Kolkata'); // IST 12:00 = UTC 06:30

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'starts_at' => $istCarbon,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'starts_at' => '2024-06-15 06:30:00',
        ]);
    }

    // -------------------------------------------------------------------------
    // Round-trip: store → retrieve → serialize
    // -------------------------------------------------------------------------

    #[Test]
    public function round_trip_preserves_absolute_moment(): void
    {
        // Store via app-timezone string input (IST 17:30 = UTC 12:00)
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'starts_at' => '2024-06-15 17:30:00',
        ]);

        // Reload from DB
        $fresh = $subscription->fresh();

        // The serialized output must show IST 17:30 with +05:30 offset
        $serialized = $fresh->toArray()['starts_at'];
        $this->assertStringContainsString('17:30:00', $serialized);
        $this->assertStringContainsString('+05:30', $serialized);

        // The raw DB value must be UTC 12:00
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'starts_at' => '2024-06-15 12:00:00',
        ]);
    }

    // -------------------------------------------------------------------------
    // AppTimezoneDate explicit cast (still works when applied selectively)
    // -------------------------------------------------------------------------

    #[Test]
    public function app_timezone_date_cast_converts_input_to_utc(): void
    {
        $cast = new AppTimezoneDate;

        // IST 17:30 → UTC 12:00
        $this->assertEquals(
            '2024-06-15 12:00:00',
            $cast->set(null, 'field', '2024-06-15 17:30:00', [])
        );
    }

    #[Test]
    public function app_timezone_date_cast_returns_carbon_in_app_timezone(): void
    {
        $cast = new AppTimezoneDate;

        $carbon = $cast->get(null, 'field', '2024-06-15 12:00:00', []);

        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertEquals('17:30:00', $carbon->format('H:i:s'));
        $this->assertEquals('+05:30', $carbon->format('P'));
    }
}
