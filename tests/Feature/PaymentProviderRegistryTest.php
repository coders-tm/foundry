<?php

namespace Foundry\Tests\Feature;

use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PaymentProviderRegistryTest extends TestCase
{
    #[Test]
    public function it_can_dynamically_add_and_retrieve_a_payment_provider_via_facade()
    {
        PaymentProvider::add('custom_crypto', [
            'name' => 'Crypto Pay',
            'label' => 'Pay with Crypto',
            'enabled' => true,
            'order' => 1,
            'public_key' => 'pk_crypto_123',
            'methods' => ['usdt', 'btc'],
        ]);

        $this->assertTrue(PaymentProvider::has('custom_crypto'));

        $config = PaymentProvider::find('custom_crypto');
        $this->assertNotNull($config);
        $this->assertEquals('custom_crypto', $config['provider']);
        $this->assertEquals('Crypto Pay', $config['name']);
        $this->assertEquals(['usdt', 'btc'], $config['methods']);

        $enabled = PaymentProvider::enabled();
        $this->assertTrue($enabled->has('custom_crypto'));

        $publicProviders = PaymentProvider::toPublic();
        $cryptoPublic = $publicProviders->firstWhere('provider', 'custom_crypto');
        $this->assertNotNull($cryptoPublic);
        $this->assertEquals('Crypto Pay', $cryptoPublic['name']);
        $this->assertEquals('Pay with Crypto', $cryptoPublic['label']);
        $this->assertEquals('pk_crypto_123', $cryptoPublic['public_key']);
        $this->assertArrayNotHasKey('key', $cryptoPublic);
        $this->assertArrayNotHasKey('client_id', $cryptoPublic);
        $this->assertArrayNotHasKey('credentials', $cryptoPublic);

        // Test removal
        PaymentProvider::remove('custom_crypto');
        $this->assertFalse(PaymentProvider::has('custom_crypto'));
        $this->assertNull(PaymentProvider::find('custom_crypto'));
    }

    #[Test]
    public function it_can_add_a_payment_provider_statically_on_registry_class()
    {
        PaymentProvider::add('custom_bank', [
            'name' => 'Bank Direct',
            'enabled' => true,
        ]);

        $this->assertTrue(PaymentProvider::has('custom_bank'));
        $this->assertEquals('Bank Direct', PaymentProvider::find('custom_bank')['name']);

        PaymentProvider::remove('custom_bank');
        $this->assertFalse(PaymentProvider::has('custom_bank'));
    }

    #[Test]
    public function it_can_filter_public_providers_to_only_mandateable_gateways()
    {
        PaymentProvider::add('stripe', [
            'name' => 'Stripe',
            'enabled' => true,
            'order' => 1,
            'key' => 'pk_stripe_123',
        ]);

        PaymentProvider::add('custom_unsupported', [
            'name' => 'Custom One-Time',
            'enabled' => true,
            'order' => 2,
        ]);

        $publicProviders = PaymentProvider::toPublic();
        $this->assertNotNull($publicProviders->firstWhere('provider', 'stripe'));
        $this->assertNotNull($publicProviders->firstWhere('provider', 'custom_unsupported'));

        $mandateableProviders = PaymentProvider::toPublicMandateable();
        $this->assertNotNull($mandateableProviders->firstWhere('provider', 'stripe'));
        $this->assertNull($mandateableProviders->firstWhere('provider', 'custom_unsupported'));

        PaymentProvider::remove('stripe');
        PaymentProvider::remove('custom_unsupported');
    }
}
