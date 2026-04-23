<?php

namespace Tests\Unit\Payment;

use Foundry\Models\PaymentMethod;
use Foundry\Payment\Processors\FlutterwaveProcessor;
use Foundry\Payment\Processors\KlarnaProcessor;
use Foundry\Payment\Processors\ManualProcessor;
use Foundry\Payment\Processors\MercadoPagoProcessor;
use Foundry\Payment\Processors\PaypalProcessor;
use Foundry\Payment\Processors\PaystackProcessor;
use Foundry\Payment\Processors\RazorpayProcessor;
use Foundry\Payment\Processors\StripeProcessor;
use Foundry\Payment\Processors\WalletProcessor;
use Foundry\Payment\Processors\XenditProcessor;
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class SupportedCurrenciesTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed payment methods
        PaymentMethod::factory()->stripe()->create();
        PaymentMethod::factory()->paypal()->create();
        PaymentMethod::factory()->razorpay()->create();
        PaymentMethod::factory()->manual()->create();
        PaymentMethod::factory()->flutterwave()->create();
        PaymentMethod::factory()->klarna()->create();

        // Seed others explicitly with required credentials
        PaymentMethod::factory()->create([
            'provider' => PaymentMethod::XENDIT,
            'name' => 'Xendit',
            'credentials' => collect([
                ['key' => 'PUBLIC_KEY', 'value' => 'xendit_public_key', 'publish' => true],
                ['key' => 'SECRET_KEY', 'value' => 'xendit_secret_key', 'publish' => false],
            ]),
        ]);

        PaymentMethod::factory()->create([
            'provider' => PaymentMethod::PAYSTACK,
            'name' => 'Paystack',
            'credentials' => collect([
                ['key' => 'PUBLIC_KEY', 'value' => 'paystack_public_key', 'publish' => true],
                ['key' => 'SECRET_KEY', 'value' => 'paystack_secret_key', 'publish' => false],
            ]),
        ]);

        PaymentMethod::factory()->create([
            'provider' => PaymentMethod::MERCADOPAGO,
            'name' => 'MercadoPago',
            'credentials' => collect([
                ['key' => 'PUBLIC_KEY', 'value' => 'mp_public_key', 'publish' => true],
                ['key' => 'ACCESS_TOKEN', 'value' => 'mp_access_token', 'publish' => false],
            ]),
        ]);

        PaymentMethod::factory()->create(['provider' => PaymentMethod::WALLET, 'name' => 'Wallet']);
    }

    #[Test]
    public function stripe_processor_has_supported_currencies()
    {

        $processor = new StripeProcessor;
        $this->assertNotEmpty($processor->supportedCurrencies());
        $this->assertContains('USD', $processor->supportedCurrencies());
    }

    #[Test]
    public function paypal_processor_has_supported_currencies()
    {
        $processor = new PaypalProcessor;
        $this->assertNotEmpty($processor->supportedCurrencies());
        $this->assertContains('USD', $processor->supportedCurrencies());
    }

    #[Test]
    public function klarna_processor_has_supported_currencies()
    {
        $processor = new KlarnaProcessor;
        $this->assertNotEmpty($processor->supportedCurrencies());
        $this->assertContains('USD', $processor->supportedCurrencies());
    }

    #[Test]
    public function xendit_processor_has_supported_currencies()
    {
        $processor = new XenditProcessor;
        $this->assertNotEmpty($processor->supportedCurrencies());
        $this->assertContains('IDR', $processor->supportedCurrencies());
    }

    #[Test]
    public function paystack_processor_has_supported_currencies()
    {
        $processor = new PaystackProcessor;
        $this->assertNotEmpty($processor->supportedCurrencies());
        $this->assertContains('NGN', $processor->supportedCurrencies());
    }

    #[Test]
    public function razorpay_processor_has_supported_currencies()
    {
        $processor = new RazorpayProcessor;
        $this->assertNotEmpty($processor->supportedCurrencies());
        $this->assertContains('INR', $processor->supportedCurrencies());
    }

    #[Test]
    public function flutterwave_processor_has_supported_currencies()
    {
        $processor = new FlutterwaveProcessor;
        $this->assertNotEmpty($processor->supportedCurrencies());
        $this->assertContains('NGN', $processor->supportedCurrencies());
    }

    #[Test]
    public function mercadopago_processor_has_supported_currencies()
    {
        $processor = new MercadoPagoProcessor;
        $this->assertNotEmpty($processor->supportedCurrencies());
        $this->assertContains('BRL', $processor->supportedCurrencies());
    }

    #[Test]
    public function manual_processor_supports_all_currencies()
    {
        $processor = new ManualProcessor;
        $this->assertEmpty($processor->supportedCurrencies());
    }

    #[Test]
    public function wallet_processor_supports_all_currencies()
    {
        $processor = new WalletProcessor;
        $this->assertEmpty($processor->supportedCurrencies());
    }
}
