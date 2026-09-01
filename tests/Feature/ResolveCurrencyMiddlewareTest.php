<?php

uses(Foundry\Tests\TestCase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Config::set('app.currency', 'USD');

    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.85]);
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.73]);
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'INR'], ['rate' => 85.0]);

    \Illuminate\Support\Facades\Route::middleware(['resolve.ip', 'resolve.currency'])->get('/test-currency', function () {
        return response()->json([
            'currency' => \Foundry\Facades\Currency::code(),
            'rate' => \Foundry\Facades\Currency::rate(),
        ]);
    });
});

it('skips currency resolution for admin users', function () {
    $admin = \Foundry\Models\Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});

it('uses saved currency for authenticated user', function () {
    $user = \Foundry\Models\User::factory()->create();
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
    $user = \Foundry\Models\User::factory()->create();
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
    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'IN';
    $position->countryName = 'India';

    \Stevebauman\Location\Facades\Location::shouldReceive('get')
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
    $user = \Foundry\Models\User::factory()->create();
    $user->currency = 'EUR';
    $user->save();
    $this->actingAs($user, 'user');

    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'IN';
    $position->countryName = 'India';

    \Stevebauman\Location\Facades\Location::shouldReceive('get')
        ->andReturn($position);

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'EUR',
        'rate' => 0.85,
    ]);
});

it('handles user without address gracefully', function () {
    $user = \Foundry\Models\User::factory()->create();
    $this->actingAs($user, 'user');

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});

it('handles empty user currency gracefully', function () {
    $user = \Foundry\Models\User::factory()->create();
    $this->actingAs($user, 'user');

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});

it('does not persist currency when same as base', function () {
    $user = \Foundry\Models\User::factory()->create();

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
    $this->assertNull($user->currency);
});

it('works with invalid country code header', function () {
    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'INVALID';
    $position->countryName = 'Invalid Country';

    \Stevebauman\Location\Facades\Location::shouldReceive('get')
        ->once()
        ->andReturn($position);

    $response = $this->getJson('/test-currency');

    $response->assertOk();
    $response->assertJson([
        'currency' => 'USD',
        'rate' => 1.0,
    ]);
});
