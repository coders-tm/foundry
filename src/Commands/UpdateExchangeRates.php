<?php

namespace Foundry\Commands;

use Foundry\Models\ExchangeRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'foundry:update-exchange-rates {--base= : Base currency code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rates from external API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $base = $this->option('base') ?: config('app.currency', 'USD');
        $this->info("Fetching exchange rates for base: {$base}");

        // Using open.er-api.com as a free alternative to Google (which has no public API)
        // You can replace this URL with your specific Google API endpoint if available.
        $url = "https://open.er-api.com/v6/latest/{$base}";

        try {
            $response = Http::get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch rates: '.$response->body());
                Log::error('Exchange Rate Update Failed', ['error' => $response->body()]);

                return Command::FAILURE;
            }

            $data = $response->json();
            $rates = $data['rates'] ?? [];

            if (empty($rates)) {
                $this->error('No rates found in response.');

                return Command::FAILURE;
            }

            $existingRates = ExchangeRate::all();

            $count = 0;
            foreach ($existingRates as $exchangeRate) {
                $currency = $exchangeRate->currency;

                if (isset($rates[$currency])) {
                    $exchangeRate->update([
                        'rate' => (float) $rates[$currency],
                    ]);
                    $count++;
                }
            }

            // Ensure base currency exists at rate 1
            ExchangeRate::updateOrCreate(['currency' => strtoupper($base)], [
                'rate' => 1.0,
            ]);

            $this->info("Successfully updated {$count} exchange rates.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Exception: '.$e->getMessage());
            Log::error('Exchange Rate Update Exception', ['exception' => $e]);

            return Command::FAILURE;
        }
    }
}
