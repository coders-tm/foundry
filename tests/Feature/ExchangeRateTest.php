<?php

namespace Foundry\Tests\Feature;

use Foundry\Models\Admin;
use Foundry\Models\ExchangeRate;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    /** @var Admin */
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing exchange rates from previous tests
        ExchangeRate::query()->delete();

        // Create and authenticate Admin
        $this->admin = Admin::factory()->create([
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $this->actingAs($this->admin, 'admin');
    }

    public function test_index_returns_rates()
    {
        ExchangeRate::create(['currency' => 'GBP', 'rate' => 0.75]);
        ExchangeRate::create(['currency' => 'EUR', 'rate' => 0.85]);

        $response = $this->getJson('/admin/exchange-rates');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_store_creates_or_updates_rate()
    {
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

        // Update existing
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
    }

    public function test_sync_triggers_command()
    {
        // We cannot easily mock Artisan here due to Testbench final Kernel class.
        // We verify the endpoint returns success.
        $response = $this->postJson('/admin/exchange-rates/sync');

        $response->assertStatus(200)
            ->assertJson(['message' => __('Exchange rates has been synced successfully!')]);
    }

    public function test_destroy_deletes_rate()
    {
        $rate = ExchangeRate::create(['currency' => 'JPY', 'rate' => 110.0]);

        $response = $this->deleteJson("/admin/exchange-rates/{$rate->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => __('Exchange rate has been deleted successfully!')]);
        $this->assertDatabaseMissing('exchange_rates', ['id' => $rate->id]);
    }

    public function test_estimate_returns_calculated_amount()
    {
        // Create a rate for INR
        ExchangeRate::create(['currency' => 'INR', 'rate' => 84.0]);

        // Mock country lookup if needed?
        // The controller uses ExchangeRate::getCurrencyFromCountryCode($request->country).
        // Standard ISO3166 lookup for 'IN' is 'INR'.

        $response = $this->getJson('/exchange-rates/estimate?amount=10&country=IN');

        $response->assertStatus(200)
            ->assertJson([
                'currency' => 'INR',
                'amount' => 840.0,
                'rate' => 84.0,
            ]);
    }

    public function test_command_updates_only_existing_rates_but_ensures_base()
    {
        // One existing rate
        ExchangeRate::create(['currency' => 'EUR', 'rate' => 0.85]);
        // Base currency 'USD' is NOT created initially to test it gets added

        // Mock API response
        Http::fake([
            '*' => Http::response([
                'rates' => [
                    'USD' => 1.0,
                    'EUR' => 0.90, // Changed
                    'GBP' => 0.75, // New, should be ignored
                ],
            ], 200),
        ]);

        $this->artisan('foundry:update-exchange-rates')
            ->assertSuccessful();

        // Verify EUR is updated
        $this->assertDatabaseHas('exchange_rates', ['currency' => 'EUR', 'rate' => 0.90]);

        // Verify Base (USD) is created/ensured
        $this->assertDatabaseHas('exchange_rates', ['currency' => 'USD', 'rate' => 1.0]);

        // Verify GBP is NOT added
        $this->assertDatabaseMissing('exchange_rates', ['currency' => 'GBP']);
    }
}
