<?php

namespace Tests\Feature\Payment;

use Foundry\Payment\Mappers\FlutterwavePayment;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Foundry\Payment\Processors\FlutterwaveProcessor;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;

class FlutterwaveProcessorTest extends FeatureTestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip all tests if Flutterwave credentials are not configured
        if (! env('FLUTTERWAVE_CLIENT_SECRET')) {
            $this->markTestSkipped('Flutterwave credentials not configured. Set FLUTTERWAVE_CLIENT_SECRET in phpunit.xml');
        }
    }

    #[Test]
    public function it_creates_flutterwave_payment_method_with_correct_configuration()
    {
        $provider = PaymentProvider::find('flutterwave');

        $this->assertNotNull($provider);
        $this->assertEquals('flutterwave', $provider['provider']);
        $this->assertTrue($provider['active']);
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
            $transaction
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
            $transaction
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
            $transaction
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
            $transaction
        );

        $metadata = $payment->getMetadata();
        $this->assertEquals('banktransfer', $metadata['payment_method_type']);

        $this->assertEquals('Banktransfer', $payment->toString());
    }
}
