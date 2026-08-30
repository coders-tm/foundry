<?php

namespace Tests\Feature\Payment;

use Foundry\Foundry;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\Feature\FeatureTestCase;
use GoCardlessPro\Client;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class GocardlessIntegrationTest extends FeatureTestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if GoCardless credentials are not configured
        if (! env('GOCARDLESS_ACCESS_TOKEN')) {
            $this->markTestSkipped('GoCardless credentials not configured. Set GOCARDLESS_ACCESS_TOKEN in phpunit.xml');
        }

        config(['foundry.payment_providers.gocardless.enabled' => true]);
    }

    #[Test]
    public function it_retrieves_gocardless_provider_config()
    {
        $gocardless = PaymentProvider::find(PaymentProvider::GOCARDLESS);

        $this->assertNotNull($gocardless);
        $this->assertEquals(PaymentProvider::GOCARDLESS, $gocardless['provider']);
    }

    #[Test]
    public function it_creates_gocardless_client_instance()
    {
        $client = Foundry::gocardless();

        $this->assertInstanceOf(Client::class, $client);
    }

    #[Test]
    public function it_supports_multiple_country_payment_schemes()
    {
        $schemes = config('foundry.payment_providers.gocardless.schemes');

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
        $token = config('foundry.payment_providers.gocardless.access_token');

        $provider = PaymentProvider::find(PaymentProvider::GOCARDLESS);

        // Token should be a non-empty string
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }
}
