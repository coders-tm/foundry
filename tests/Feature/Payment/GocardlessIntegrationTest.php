<?php

namespace Tests\Feature\Payment;

use Foundry\Foundry;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Services\Gateways\GoCardlessSubscriptionGateway;
use Foundry\Services\GatewaySubscriptionFactory;
use Foundry\Tests\Feature\FeatureTestCase;
use GoCardlessPro\Client;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class GocardlessIntegrationTest extends FeatureTestCase
{
    use WithFaker;

    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if GoCardless credentials are not configured
        if (! env('GOCARDLESS_ACCESS_TOKEN') || ! env('GOCARDLESS_WEBHOOK_SECRET')) {
            $this->markTestSkipped('GoCardless credentials not configured. Set GOCARDLESS_ACCESS_TOKEN and GOCARDLESS_WEBHOOK_SECRET in phpunit.xml');
        }

        // Get GoCardless payment method created by seeder (don't filter by enabled status)
        $paymentMethod = PaymentMethod::byProvider(PaymentMethod::GOCARDLESS);

        if (! $paymentMethod) {
            $this->markTestSkipped('GoCardless payment method not found. Run seeders first.');
        }

        // Enable the payment method for testing
        $paymentMethod->update(['active' => true, 'test_mode' => true]);
        PaymentMethod::updateProviderCache(PaymentMethod::GOCARDLESS);

        $this->paymentMethod = $paymentMethod;
    }

    #[Test]
    public function it_retrieves_gocardless_via_static_method()
    {
        $gocardless = PaymentMethod::gocardless();

        $this->assertNotNull($gocardless);
        $this->assertEquals($this->paymentMethod->id, $gocardless->id);
    }

    #[Test]
    public function it_creates_gocardless_client_instance()
    {
        $client = Foundry::gocardless();

        $this->assertInstanceOf(Client::class, $client);
    }

    #[Test]
    public function it_creates_subscription_gateway_for_gocardless()
    {
        // Create a real subscription using factory
        $user = User::factory()->create();
        $plan = Plan::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'gocardless',
            'status' => 'active',
        ]);

        $gateway = GatewaySubscriptionFactory::make($subscription);

        $this->assertInstanceOf(GoCardlessSubscriptionGateway::class, $gateway);
    }

    #[Test]
    public function it_handles_gocardless_payment_methods_array()
    {
        $this->assertIsArray($this->paymentMethod->methods);

        // Methods can be objects or strings
        $methodKeys = collect($this->paymentMethod->methods)->map(function ($method) {
            return is_array($method) ? $method['key'] : $method;
        })->toArray();

        $this->assertContains('direct_debit', $methodKeys);
    }

    #[Test]
    public function it_updates_cache_when_gocardless_credentials_change()
    {
        // Update access token
        $newToken = 'sandbox_'.$this->faker->sha256();

        $this->paymentMethod->update([
            'credentials' => collect([
                ['key' => 'ACCESS_TOKEN', 'value' => $newToken, 'publish' => false],
                ['key' => 'WEBHOOK_SECRET', 'value' => 'new-webhook-secret', 'publish' => false],
            ]),
        ]);

        // Reload the model to get fresh data
        $this->paymentMethod->refresh();

        // Cache should be updated automatically via model observer
        $this->assertEquals($newToken, config('gocardless.access_token'));
    }

    #[Test]
    public function it_supports_multiple_country_payment_schemes()
    {
        $schemes = config('gocardless.schemes');

        // UK - BACS Direct Debit
        $this->assertEquals('bacs', $schemes['GB']);

        // Europe - SEPA Core Direct Debit
        $this->assertEquals('sepa_core', $schemes['DE']); // Germany
        $this->assertEquals('sepa_core', $schemes['FR']); // France
        $this->assertEquals('sepa_core', $schemes['ES']); // Spain
        $this->assertEquals('sepa_core', $schemes['IT']); // Italy
        $this->assertEquals('sepa_core', $schemes['NL']); // Netherlands
        $this->assertEquals('sepa_core', $schemes['BE']); // Belgium

        // Australia & New Zealand
        $this->assertEquals('becs', $schemes['AU']);
        $this->assertEquals('becs_nz', $schemes['NZ']);

        // North America
        $this->assertEquals('ach', $schemes['US']); // ACH in USA
        $this->assertEquals('pad', $schemes['CA']); // PAD in Canada

        // Sweden
        $this->assertEquals('autogiro', $schemes['SE']);
    }

    #[Test]
    public function it_validates_access_token_format()
    {
        $token = $this->paymentMethod->getConfigs()['ACCESS_TOKEN'];

        // Sandbox tokens should start with 'sandbox_'
        if ($this->paymentMethod->test_mode) {
            $this->assertStringStartsWith('sandbox_', $token);
        }

        // Token should be a non-empty string
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }
}
