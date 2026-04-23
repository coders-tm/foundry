<?php

namespace Foundry\Tests\Feature;

use Foundry\Facades\Currency;
use Foundry\Models\Admin;
use Foundry\Models\ExchangeRate;
use Foundry\Models\User;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

class ResolveCurrencyMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.currency', 'USD');

        // Create exchange rates for testing
        ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.85]);
        ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.73]);
        ExchangeRate::updateOrCreate(['currency' => 'INR'], ['rate' => 85.0]);

        // Define test routes with middleware - resolve.ip must run before resolve.currency
        Route::middleware(['resolve.ip', 'resolve.currency'])->get('/test-currency', function () {
            return response()->json([
                'currency' => Currency::code(),
                'rate' => Currency::rate(),
            ]);
        });
    }

    #[Test]
    public function it_skips_currency_resolution_for_admin_users()
    {
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        // Admin should get base currency (USD)
        $response->assertJson([
            'currency' => 'USD',
            'rate' => 1.0,
        ]);
    }

    #[Test]
    public function it_uses_saved_currency_for_authenticated_user()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->currency = 'EUR';
        $user->save();
        $this->actingAs($user, 'user');

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        $response->assertJson([
            'currency' => 'EUR',
            'rate' => 0.85,
        ]);
    }

    #[Test]
    public function it_resolves_currency_from_user_address_country()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->currency = 'GBP';
        $user->save();
        $this->actingAs($user, 'user');

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        $response->assertJson([
            'currency' => 'GBP',
            'rate' => 0.73,
        ]);
    }

    #[Test]
    public function it_resolves_currency_from_ip_location_for_guest_users()
    {
        // Create a Position object with Indian location
        $position = new Position;
        $position->countryCode = 'IN';
        $position->countryName = 'India';

        // Mock the Location facade to return Indian location
        Location::shouldReceive('get')
            ->once()
            ->andReturn($position);

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        $response->assertJson([
            'currency' => 'INR',
            'rate' => 85.0,
        ]);
    }

    #[Test]
    public function it_uses_cf_ipcountry_header_for_guest_users()
    {
        $response = $this->getJson('/test-currency', [
            'CF-IPCountry' => 'IN',
        ]);

        $response->assertOk();
        $response->assertJson([
            'currency' => 'INR',
            'rate' => 85.0,
        ]);
    }

    #[Test]
    public function it_falls_back_to_base_currency_when_no_country_detected()
    {
        $response = $this->getJson('/test-currency');

        $response->assertOk();
        $response->assertJson([
            'currency' => 'USD',
            'rate' => 1.0,
        ]);
    }

    #[Test]
    public function it_prioritizes_user_currency_over_cf_ipcountry()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user->currency = 'EUR';
        $user->save();
        $this->actingAs($user, 'user');

        // Mock Location to return different country
        $position = new Position;
        $position->countryCode = 'IN';
        $position->countryName = 'India';

        Location::shouldReceive('get')
            ->andReturn($position);

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        // User's saved currency (EUR) should take precedence over IP location
        $response->assertJson([
            'currency' => 'EUR',
            'rate' => 0.85,
        ]);
    }

    #[Test]
    public function it_handles_user_without_address_gracefully()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'user');

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        // Should fallback to base currency
        $response->assertJson([
            'currency' => 'USD',
            'rate' => 1.0,
        ]);
    }

    #[Test]
    public function it_handles_empty_user_currency_gracefully()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'user');

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        // Should fallback to base currency when no currency set
        $response->assertJson([
            'currency' => 'USD',
            'rate' => 1.0,
        ]);
    }

    #[Test]
    public function it_does_not_persist_currency_when_same_as_base()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create address for US (base currency)
        $user->address()->create([
            'country' => 'United States',
            'country_code' => 'US',
        ]);

        $this->actingAs($user, 'user');

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        $response->assertJson([
            'currency' => 'USD',
            'rate' => 1.0,
        ]);

        // Verify currency was NOT persisted (since it's the same as base)
        $user->refresh();
        $this->assertNull($user->currency);
    }

    #[Test]
    public function it_works_with_invalid_country_code_header()
    {
        // Mock Location to return invalid country code
        $position = new Position;
        $position->countryCode = 'INVALID';
        $position->countryName = 'Invalid Country';

        Location::shouldReceive('get')
            ->once()
            ->andReturn($position);

        $response = $this->getJson('/test-currency');

        $response->assertOk();
        // Should fallback to base currency for invalid country
        $response->assertJson([
            'currency' => 'USD',
            'rate' => 1.0,
        ]);
    }
}
