<?php

namespace Tests\Feature\Payment;

use Foundry\Models\PaymentMethod;
use Foundry\Models\Shop\Product;
use Foundry\Models\Shop\Product\Variant;
use Foundry\Payment\Mappers\FlutterwavePayment;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Foundry\Payment\Processors\FlutterwaveProcessor;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class FlutterwaveProcessorTest extends FeatureTestCase
{
    use WithFaker;

    protected PaymentMethod $paymentMethod;

    protected Product $product;

    protected Variant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if Flutterwave credentials are not configured
        if (! env('FLUTTERWAVE_CLIENT_ID') || ! env('FLUTTERWAVE_CLIENT_SECRET') || ! env('FLUTTERWAVE_ENCRYPTION_KEY')) {
            $this->markTestSkipped('Flutterwave credentials not configured. Set FLUTTERWAVE_CLIENT_ID, FLUTTERWAVE_CLIENT_SECRET, and FLUTTERWAVE_ENCRYPTION_KEY in phpunit.xml');
        }

        // Get Flutterwave payment method created by seeder (don't filter by enabled status)
        $paymentMethod = PaymentMethod::byProvider(PaymentMethod::FLUTTERWAVE);

        if (! $paymentMethod) {
            $this->markTestSkipped('Flutterwave payment method not found. Run seeders first.');
        }

        // Enable the payment method for testing
        $paymentMethod->update(['active' => true, 'test_mode' => true]);

        $this->paymentMethod = $paymentMethod;

        // Create test product and variant
        $this->product = Product::factory()->create([
            'title' => 'Test Product for Flutterwave',
            'has_variant' => true,
        ]);

        $this->variant = Variant::factory()->create([
            'product_id' => $this->product->id,
            'price' => 50.00,
            'sku' => 'FLW-TEST-001',
        ]);
    }

    #[Test]
    public function it_creates_flutterwave_payment_method_with_correct_configuration()
    {
        $this->assertNotNull($this->paymentMethod);
        $this->assertEquals('flutterwave', $this->paymentMethod->provider);
        $this->assertTrue($this->paymentMethod->active);

        // Verify credentials are stored correctly (from env)
        $configs = $this->paymentMethod->getConfigs();
        $this->assertArrayHasKey('CLIENT_ID', $configs);
        $this->assertArrayHasKey('CLIENT_SECRET', $configs);
        $this->assertArrayHasKey('ENCRYPTION_KEY', $configs);
        $this->assertNotEmpty($configs['CLIENT_ID']);
        $this->assertNotEmpty($configs['CLIENT_SECRET']);
        $this->assertNotEmpty($configs['ENCRYPTION_KEY']);
    }

    #[Test]
    public function it_syncs_flutterwave_configuration_to_laravel_config()
    {
        // Configuration should be synced after updateProviderCache
        $this->assertEquals($this->paymentMethod->id, config('flutterwave.id'));
        $this->assertNotEmpty(config('flutterwave.public_key'));
        $this->assertNotEmpty(config('flutterwave.secret_key'));
        $this->assertNotEmpty(config('flutterwave.encryption_key'));
        $this->assertTrue(config('flutterwave.enabled'));
    }

    #[Test]
    public function it_checks_processor_supports_flutterwave()
    {
        $this->assertTrue(Processor::isSupported('flutterwave'));
    }

    #[Test]
    public function it_creates_flutterwave_processor_instance()
    {
        $processor = Processor::make('flutterwave');

        $this->assertInstanceOf(FlutterwaveProcessor::class, $processor);
        $this->assertEquals('flutterwave', $processor->getProvider());
    }

    #[Test]
    public function it_includes_flutterwave_in_public_payment_methods()
    {
        $publicMethods = PaymentMethod::toPublic();

        $flutterwaveMethod = $publicMethods->firstWhere('provider', 'flutterwave');

        $this->assertNotNull($flutterwaveMethod);
        $this->assertEquals('Flutterwave', $flutterwaveMethod['name']);
        $this->assertEquals('flutterwave', $flutterwaveMethod['provider']);
        $this->assertArrayHasKey('CLIENT_ID', $flutterwaveMethod['credentials']);
        $this->assertNotEmpty($flutterwaveMethod['credentials']['CLIENT_ID']);

        // Secret and encryption keys should not be published
        $this->assertArrayNotHasKey('CLIENT_SECRET', $flutterwaveMethod['credentials']);
        $this->assertArrayNotHasKey('ENCRYPTION_KEY', $flutterwaveMethod['credentials']);
    }

    #[Test]
    public function it_can_disable_and_enable_flutterwave_payment_method()
    {
        // Disable
        $this->paymentMethod->update(['active' => false]);
        PaymentMethod::updateProviderCache(PaymentMethod::FLUTTERWAVE);

        $this->assertFalse(PaymentMethod::has('flutterwave'));
        $this->assertNull(PaymentMethod::flutterwave());

        // Enable
        $this->paymentMethod->update(['active' => true]);
        PaymentMethod::updateProviderCache(PaymentMethod::FLUTTERWAVE);

        $this->assertTrue(PaymentMethod::has('flutterwave'));
        $this->assertNotNull(PaymentMethod::flutterwave());
    }

    #[Test]
    public function it_updates_cache_when_flutterwave_credentials_change()
    {
        // Update credentials
        $newClientId = 'new-client-id-'.$this->faker->uuid();

        $this->paymentMethod->update([
            'credentials' => collect([
                ['key' => 'CLIENT_ID', 'value' => $newClientId, 'publish' => true],
                ['key' => 'CLIENT_SECRET', 'value' => 'new-secret', 'publish' => false],
                ['key' => 'ENCRYPTION_KEY', 'value' => 'new-encryption-key', 'publish' => false],
            ]),
        ]);

        // Reload the model to get fresh data
        $this->paymentMethod->refresh();

        // Cache should be updated automatically via model observer
        $this->assertEquals($newClientId, config('flutterwave.public_key'));
    }

    #[Test]
    public function it_extracts_card_payment_metadata_from_transaction()
    {
        $transaction = [
            'id' => 12345,
            'tx_ref' => 'FLW-TEST-123',
            'status' => 'successful',
            'payment_type' => 'card',
            'card' => [
                'first_6digits' => '539983',
                'last_4digits' => '8381',
                'issuer' => 'MASTERCARD',
                'country' => 'NG',
                'type' => 'VISA',
                'expiry' => '09/32',
            ],
            'amount' => 1000,
            'currency' => 'NGN',
        ];

        // Use mapper to extract metadata
        $payable = Payable::make([
            'grand_total' => 10.00,
        ]);
        $payment = new FlutterwavePayment(
            $transaction,
            $this->paymentMethod
        );

        $metadata = $payment->getMetadata();
        $this->assertEquals('card', $metadata['payment_method_type']);
        $this->assertEquals('8381', $metadata['last_four']);
        $this->assertEquals('VISA', $metadata['card_brand']); // Flutterwave returns uppercase
        $this->assertEquals('NG', $metadata['country']);

        $this->assertEquals('VISA •••• 8381 (MASTERCARD)', $payment->toString()); // Uppercase from Flutterwave API
    }

    #[Test]
    public function it_extracts_mobile_money_metadata_from_transaction()
    {
        $transaction = [
            'id' => 12346,
            'tx_ref' => 'FLW-MOBILEMONEY-123',
            'status' => 'successful',
            'payment_type' => 'mobilemoney',
            'customer' => [
                'phone_number' => '+233123456789',
            ],
            'amount' => 500,
            'currency' => 'GHS',
        ];

        // Use mapper to extract metadata
        $payable = Payable::make([
            'grand_total' => 5.00,
        ]);
        $payment = new FlutterwavePayment(
            $transaction,
            $this->paymentMethod
        );

        $metadata = $payment->getMetadata();
        $this->assertEquals('mobilemoney', $metadata['payment_method_type']);
        $this->assertEquals('+233123456789', $metadata['mobile_number']);

        $this->assertEquals('Mobilemoney (+233123456789)', $payment->toString());
    }

    #[Test]
    public function it_extracts_ussd_payment_metadata_from_transaction()
    {
        $transaction = [
            'id' => 12347,
            'tx_ref' => 'FLW-USSD-123',
            'status' => 'successful',
            'payment_type' => 'ussd',
            'amount' => 2000,
            'currency' => 'NGN',
        ];

        // Use mapper to extract metadata
        $payable = Payable::make([
            'grand_total' => 20.00,
        ]);
        $payment = new FlutterwavePayment(
            $transaction,
            $this->paymentMethod
        );

        $metadata = $payment->getMetadata();
        $this->assertEquals('ussd', $metadata['payment_method_type']);

        $this->assertEquals('Ussd', $payment->toString());
    }

    #[Test]
    public function it_extracts_bank_transfer_metadata_from_transaction()
    {
        $transaction = [
            'id' => 12348,
            'tx_ref' => 'FLW-TEST-012',
            'status' => 'successful',
            'payment_type' => 'banktransfer',
            'account' => [
                'bank_code' => 'ACCESS',
                'account_number' => '0123456789',
            ],
            'amount' => 5000,
            'currency' => 'NGN',
        ];

        // Use mapper to extract metadata
        $payable = Payable::make([
            'grand_total' => 50.00,
        ]);
        $payment = new FlutterwavePayment(
            $transaction,
            $this->paymentMethod
        );

        $metadata = $payment->getMetadata();
        $this->assertEquals('banktransfer', $metadata['payment_method_type']);

        $this->assertEquals('Banktransfer', $payment->toString());
    }
}
