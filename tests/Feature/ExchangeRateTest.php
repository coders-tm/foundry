<?php

uses(Foundry\Tests\TestCase::class)->use(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Foundry\Models\ExchangeRate::query()->delete();

    $this->admin = \Foundry\Models\Admin::factory()->create([
        'is_active' => true,
        'is_super_admin' => true,
    ]);
    $this->actingAs($this->admin, 'admin');
});

it('index returns rates', function () {
    \Foundry\Models\ExchangeRate::create(['currency' => 'GBP', 'rate' => 0.75]);
    \Foundry\Models\ExchangeRate::create(['currency' => 'EUR', 'rate' => 0.85]);

    $response = $this->getJson('/admin/exchange-rates');

    $response->assertStatus(200)
        ->assertJsonCount(2);
});

it('store creates or updates rate', function () {
    $response = $this->postJson('/admin/exchange-rates', [
        'currency' => 'CAD',
        'rate' => 1.25,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => ['currency' => 'CAD', 'rate' => 1.25],
            'message' => __('Exchange rate has been saved successfully!'),
        ]);
    $this->assertDatabaseHas('exchange_rates', ['currency' => 'CAD', 'rate' => 1.25]);

    $response = $this->postJson('/admin/exchange-rates', [
        'currency' => 'CAD',
        'rate' => 1.30,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => ['currency' => 'CAD', 'rate' => 1.30],
            'message' => __('Exchange rate has been saved successfully!'),
        ]);
    $this->assertDatabaseHas('exchange_rates', ['currency' => 'CAD', 'rate' => 1.30]);
});

it('sync triggers command', function () {
    $response = $this->postJson('/admin/exchange-rates/sync');

    $response->assertStatus(200)
        ->assertJson(['message' => __('Exchange rates has been synced successfully!')]);
});

it('destroy deletes rate', function () {
    $rate = \Foundry\Models\ExchangeRate::create(['currency' => 'JPY', 'rate' => 110.0]);

    $response = $this->deleteJson("/admin/exchange-rates/{$rate->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => __('Exchange rate has been deleted successfully!')]);
    $this->assertDatabaseMissing('exchange_rates', ['id' => $rate->id]);
});

it('estimate returns calculated amount', function () {
    \Foundry\Models\ExchangeRate::create(['currency' => 'INR', 'rate' => 84.0]);

    $response = $this->getJson('/exchange-rates/estimate?amount=10&country=IN');

    $response->assertStatus(200)
        ->assertJson([
            'currency' => 'INR',
            'amount' => 840.0,
            'rate' => 84.0,
        ]);
});

it('command updates only existing rates but ensures base', function () {
    \Foundry\Models\ExchangeRate::create(['currency' => 'EUR', 'rate' => 0.85]);

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'rates' => [
                'USD' => 1.0,
                'EUR' => 0.90,
                'GBP' => 0.75,
            ],
        ], 200),
    ]);

    $this->artisan('foundry:update-exchange-rates')
        ->assertSuccessful();

    $this->assertDatabaseHas('exchange_rates', ['currency' => 'EUR', 'rate' => 0.90]);
    $this->assertDatabaseHas('exchange_rates', ['currency' => 'USD', 'rate' => 1.0]);
    $this->assertDatabaseMissing('exchange_rates', ['currency' => 'GBP']);
});
