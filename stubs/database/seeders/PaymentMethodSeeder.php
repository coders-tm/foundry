<?php

namespace Database\Seeders;

use Foundry\Concerns\Helpers;
use Foundry\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    use Helpers;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $paymentMethods = replace_short_code(file_get_contents(database_path('data/payment-methods.json')), [
            'api_url' => base_url('api'),
        ]);
        $paymentMethods = json_decode($paymentMethods, true);

        foreach ($paymentMethods as $paymentMethod) {
            $credentials = isset($paymentMethod['credentials']) ? $paymentMethod['credentials'] : [];

            // Map credentials from environment variables based on provider
            $credentials = $this->mapCredentialsFromEnv($paymentMethod['provider'], $credentials);

            PaymentMethod::firstOrCreate([
                'provider' => $paymentMethod['provider'],
            ], array_merge($paymentMethod, [
                'credentials' => $credentials,
            ]));
        }
    }

    /**
     * Map credentials from environment variables for the given provider.
     */
    protected function mapCredentialsFromEnv(string $provider, array $credentials): array
    {
        $envMap = $this->getEnvMapForProvider($provider);

        if (empty($envMap)) {
            return $credentials;
        }

        return collect($credentials)->map(function ($item) use ($envMap) {
            $key = $item['key'];

            if (isset($envMap[$key])) {
                $envValue = $envMap[$key];

                // Handle both direct env keys and config paths
                if (is_callable($envValue)) {
                    $value = $envValue();
                } elseif (str_starts_with($envValue, 'config:')) {
                    $value = config(substr($envValue, 7));
                } else {
                    $value = env($envValue);
                }

                // Only override if env value exists
                if ($value !== null && $value !== '') {
                    $item['value'] = $value;
                }
            }

            return $item;
        })->toArray();
    }

    /**
     * Get environment variable mapping for the given provider.
     */
    protected function getEnvMapForProvider(string $provider): array
    {
        return match ($provider) {
            PaymentMethod::STRIPE => [
                'API_KEY' => 'config:stripe.key',
                'API_SECRET' => 'config:stripe.secret',
                'WEBHOOK_SECRET' => 'config:stripe.webhook.secret',
            ],
            PaymentMethod::PAYSTACK => [
                'PUBLIC_KEY' => 'PAYSTACK_PUBLIC_KEY',
                'SECRET_KEY' => 'PAYSTACK_SECRET_KEY',
            ],
            PaymentMethod::XENDIT => [
                'PUBLIC_KEY' => 'XENDIT_PUBLIC_KEY',
                'SECRET_KEY' => 'XENDIT_SECRET_KEY',
            ],
            PaymentMethod::FLUTTERWAVE => [
                'CLIENT_ID' => 'FLUTTERWAVE_CLIENT_ID',
                'CLIENT_SECRET' => 'FLUTTERWAVE_CLIENT_SECRET',
                'ENCRYPTION_KEY' => 'FLUTTERWAVE_ENCRYPTION_KEY',
            ],
            PaymentMethod::GOCARDLESS => [
                'ACCESS_TOKEN' => 'GOCARDLESS_ACCESS_TOKEN',
                'WEBHOOK_SECRET' => 'GOCARDLESS_WEBHOOK_SECRET',
            ],
            PaymentMethod::KLARNA => [
                'API_KEY' => 'KLARNA_API_KEY',
                'API_SECRET' => 'KLARNA_API_SECRET',
            ],
            PaymentMethod::PAYPAL => [
                'CLIENT_ID' => 'PAYPAL_CLIENT_ID',
                'CLIENT_SECRET' => 'PAYPAL_CLIENT_SECRET',
            ],
            PaymentMethod::RAZORPAY => [
                'API_KEY' => 'RAZORPAY_API_KEY',
                'API_SECRET' => 'RAZORPAY_API_SECRET',
            ],
            default => [],
        };
    }
}
