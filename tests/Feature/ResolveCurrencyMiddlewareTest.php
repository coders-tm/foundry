<?php

use Foundry\Facades\Currency;
use Foundry\Models\Admin;
use Foundry\Models\ExchangeRate;
use Foundry\Models\User;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

uses(TestCase::class);

beforeEach(function () {
    Config::set('app.currency', 'USD');

    ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.85]);
    ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.73]);
    ExchangeRate::updateOrCreate(['currency' => 'INR'], ['rate' => 85.0]);

    Route::middleware(['resolve.ip', 'resolve.currency'])->get('/test-currency', function () {
        return response()->json([
            'currency' => Currency::code(),
            'rate' => Currency::rate(),
        ]);
    });
});

it('skips currency resolution for admin users', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});

it('uses saved currency for authenticated user', function () {
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
});

it('resolves currency from user address country', function () {
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
});

it('resolves currency from ip location for guest users', function () {
    $position = new Position;
    $position->countryCode = 'IN';
    $position->countryName = 'India';

    Location::shouldReceive('get')
        ->once()
        ->andReturn($position);

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'INR',
        'rate' => 85.0,
    ]);
});

it('uses cf ipcountry header for guest users', function () {
    $response = $this->getJson('/test-currency', [
        'CF-IPCountry' => 'IN',
    ]);

    $response->assertOk();
    $response->assertJson([
        'currency' => 'INR',
        'rate' => 85.0,
    ]);
});

it('falls back to base currency when no country detected', function () {
    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});

it('prioritizes user currency over cf ipcountry', function () {
    $user = User::factory()->create();
    $user->currency = 'EUR';
    $user->save();
    $this->actingAs($user, 'user');

    $position = new Position;
    $position->countryCode = 'IN';
    $position->countryName = 'India';

    Location::shouldReceive('get')
        ->andReturn($position);

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'EUR',
        'rate' => 0.85,
    ]);
});

it('handles user without address gracefully', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});

it('handles empty user currency gracefully', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});

it('does not persist currency when same as base', function () {
    $user = User::factory()->create();

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

    $user->refresh();
    expect($user->currency)->toBeNull();
});

it('works with invalid country code header', function () {
    $position = new Position;
    $position->countryCode = 'INVALID';
    $position->countryName = 'Invalid Country';

    Location::shouldReceive('get')
        ->once()
        ->andReturn($position);

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});
