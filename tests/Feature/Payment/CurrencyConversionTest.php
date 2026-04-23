<?php

namespace Tests\Feature\Payment;

use Foundry\Facades\Currency;
use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Payment\Mappers\FlutterwavePayment;
use Foundry\Payment\Mappers\KlarnaPayment;
use Foundry\Payment\Mappers\ManualPayment;
use Foundry\Payment\Mappers\MercadoPagoPayment;
use Foundry\Payment\Mappers\PaystackPayment;
use Foundry\Payment\Mappers\XenditPayment;
use Foundry\Payment\Payable;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

class CurrencyConversionTest extends FeatureTestCase
{
    use RefreshDatabase, WithFaker;

    protected PaymentMethod $paymentMethod;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base currency as USD
        Config::set('app.currency', 'USD');
        Currency::set('USD', 1.0);

        // Seed base currency rate
        ExchangeRate::firstOrCreate(
            ['currency' => 'USD'],
            ['rate' => 1.0]
        );

        // Create a test order in USD
        $this->order = Order::factory()->create([
            'grand_total' => 100.00, // Base currency
        ]);
    }

    protected function mockPaypalClient($mock)
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('paypalClient');
        $property->setAccessible(true);
        $property->setValue(null, $mock);
    }

    protected function resetPaypalClient()
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('paypalClient');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->resetPaypalClient();
        $this->resetStripeClient();
        $this->resetRazorpayClient();
        parent::tearDown();
    }

    protected function mockStripeClient($mock)
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('stripeClient');
        $property->setAccessible(true);
        $property->setValue(null, $mock);
    }

    protected function resetStripeClient()
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('stripeClient');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    protected function mockRazorpayClient($mock)
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('razorpayClient');
        $property->setAccessible(true);
        $property->setValue(null, $mock);
    }

    protected function resetRazorpayClient()
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('razorpayClient');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    #[Test]
    public function it_stores_payment_in_base_currency_when_paid_in_foreign_currency()
    {
        // 1. Set User Currency to EUR (Supported)
        // Exchange Rate: 1 USD = 0.9 EUR
        ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);

        // Mock Location to force EUR resolution
        $position = new Position;
        $position->countryCode = 'DE';
        Location::shouldReceive('get')
            ->andReturn($position);

        // Assert user currency is EUR
        Currency::resolve(['country_code' => 'DE']);
        $this->assertEquals('EUR', Currency::code());

        // Create Paypal Payment Method
        $paymentMethod = PaymentMethod::create([
            'name' => 'Paypal',
            'provider' => 'paypal',
            'active' => true,
            'credentials' => [
                ['key' => 'CLIENT_ID', 'value' => 'test_id'],
                ['key' => 'CLIENT_SECRET', 'value' => 'test_secret'],
            ],
            'test_mode' => true,
        ]);
        PaymentMethod::updateProviderCache('paypal');

        // Update Order with Billing Address
        $this->order->update([
            'billing_address' => [
                'country_code' => 'DE',
                'line1' => 'Test Strasse',
            ],
        ]);

        // 2. Mock Paypal Client
        $paypalMock = \Mockery::mock('stdClass');
        $paypalOrderId = 'ORDER-123';

        // Mock capturePaymentOrder response
        $captureResponse = [
            'id' => $paypalOrderId,
            'status' => 'COMPLETED',
            'payer' => [
                'email_address' => 'test@example.com',
                'name' => [
                    'given_name' => 'John',
                    'surname' => 'Doe',
                ],
            ],
            'purchase_units' => [
                [
                    'payments' => [
                        'captures' => [
                            [
                                'id' => 'CAP-123',
                                'status' => 'COMPLETED',
                                'amount' => [
                                    'value' => '90.00', // 100 USD * 0.9 = 90 EUR
                                    'currency_code' => 'EUR',
                                ],
                                'seller_receivable_breakdown' => [
                                    'paypal_fee' => [
                                        'value' => '3.00',
                                        'currency_code' => 'EUR',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $paypalMock->shouldReceive('capturePaymentOrder')
            ->with($paypalOrderId)
            ->once()
            ->andReturn($captureResponse);

        $this->mockPaypalClient($paypalMock);

        // 3. Call confirmPayment
        // Note: The route param is {provider}, which uses 'paypal'
        $methodId = PaymentMethod::where('provider', 'paypal')->value('id');
        $response = $this->postJson(route('payment.confirm', ['provider' => $methodId]), [
            'token' => $this->order->id,
            'paypal_order_id' => $paypalOrderId,
            'payer_id' => 'PAYER-123',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'success',
        ]);

        // 4. Verify Payment Record in DB
        // Should be stored in BASE currency (USD), which is 100.00
        $this->assertDatabaseHas('payments', [
            'paymentable_id' => $this->order->id,
            'paymentable_type' => $this->order->getMorphClass(),
            'amount' => 100.00, // EXPECTED: 100.00 (Base)
            'currency' => 'USD', // EXPECTED: USD (Base)
        ]);

        // 5. Verify Metadata
        $payment = $this->order->payments()->latest()->first();
        $metadata = $payment->metadata;

        // Gateway amount should be in EUR (90.00)
        $this->assertEquals(90.00, $metadata['gateway_amount']);
        $this->assertEquals('EUR', $metadata['gateway_currency']);

        // Ensure Transaction ID matches capture ID
        $this->assertEquals('CAP-123', $payment->transaction_id);
    }

    #[Test]
    public function it_stores_payment_in_base_currency_when_paid_in_foreign_currency_with_stripe()
    {
        // 1. Set User Currency to EUR (Supported)
        ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);

        // Mock Location to force EUR resolution
        $position = new Position;
        $position->countryCode = 'DE';
        Location::shouldReceive('get')
            ->andReturn($position);

        // Assert user currency is EUR
        Currency::resolve(['country_code' => 'DE']);
        $this->assertEquals('EUR', Currency::code());

        // Create Stripe Payment Method
        $paymentMethod = PaymentMethod::create([
            'name' => 'Stripe',
            'provider' => 'stripe',
            'active' => true,
            'credentials' => [
                ['key' => 'API_KEY', 'value' => 'pk_test_123'],
                ['key' => 'API_SECRET', 'value' => 'sk_test_123'],
            ],
            'test_mode' => true,
        ]);
        PaymentMethod::updateProviderCache('stripe');

        // Update Order with Billing Address
        $this->order->update([
            'billing_address' => [
                'country_code' => 'DE',
                'line1' => 'Test Strasse',
            ],
        ]);

        // 2. Mock Stripe Client
        $stripeMock = \Mockery::mock('Stripe\StripeClient');
        $paymentIntentsMock = \Mockery::mock();
        $stripeMock->paymentIntents = $paymentIntentsMock;

        // Mock Payment Intent Retrieval
        $intent = (object) [
            'id' => 'pi_eur_confirm_test',
            'status' => 'succeeded',
            'amount' => 9000, // 100 USD * 0.9 = 90 EUR = 9000 cents
            'currency' => 'eur',
            'charges' => (object) [
                'data' => [
                    (object) [
                        'payment_method_details' => (object) [
                            'type' => 'card',
                            'card' => (object) [
                                'brand' => 'visa',
                                'last4' => '4242',
                                'exp_month' => 12,
                                'exp_year' => 2030,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $paymentIntentsMock->shouldReceive('retrieve')
            ->with('pi_eur_confirm_test', ['expand' => ['payment_method', 'latest_charge']])
            ->once()
            ->andReturn($intent);

        $this->mockStripeClient($stripeMock);

        // 3. Call confirmPayment
        $methodId = PaymentMethod::where('provider', 'stripe')->value('id');
        $response = $this->postJson(route('payment.confirm', ['provider' => $methodId]), [
            'token' => $this->order->id,
            'payment_intent_id' => 'pi_eur_confirm_test',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'success',
        ]);

        // 4. Verify Payment Record in DB
        // Should be stored in BASE currency (USD), which is 100.00
        $this->assertDatabaseHas('payments', [
            'paymentable_id' => $this->order->id,
            'paymentable_type' => $this->order->getMorphClass(),
            'amount' => 100.00, // EXPECTED: 100.00 (Base)
            'currency' => 'USD', // EXPECTED: USD (Base)
        ]);

        // 5. Verify Metadata
        $payment = $this->order->payments()->latest()->first();
        $metadata = $payment->metadata;

        // Gateway amount should be in EUR (90.00)
        $this->assertEquals(90.00, $metadata['gateway_amount']);
        $this->assertEquals('EUR', $metadata['gateway_currency']);
        $this->assertEquals('pi_eur_confirm_test', $payment->transaction_id);
    }

    #[Test]
    public function it_stores_payment_in_base_currency_when_paid_in_foreign_currency_with_razorpay()
    {
        // 1. Set User Currency to INR (Supported)
        ExchangeRate::updateOrCreate(['currency' => 'INR'], ['rate' => 80.0]);

        // Mock Location to force INR resolution
        $position = new Position;
        $position->countryCode = 'IN';
        Location::shouldReceive('get')
            ->andReturn($position);

        // Assert user currency is INR
        Currency::resolve(['country_code' => 'IN']);
        $this->assertEquals('INR', Currency::code());

        // Create Razorpay Payment Method
        $paymentMethod = PaymentMethod::create([
            'name' => 'Razorpay',
            'provider' => 'razorpay',
            'active' => true,
            'credentials' => [
                ['key' => 'API_KEY', 'value' => 'rzp_test_123'],
                ['key' => 'API_SECRET', 'value' => 'rzp_secret_123'],
            ],
            'test_mode' => true,
        ]);
        PaymentMethod::updateProviderCache('razorpay');

        // Update Order with Billing Address
        $this->order->update([
            'billing_address' => [
                'country_code' => 'IN',
                'line1' => 'Test Road',
            ],
        ]);

        // 2. Mock Razorpay Client
        $razorpayMock = \Mockery::mock('Razorpay\Api\Api');
        $utilityMock = \Mockery::mock();
        $paymentServiceMock = \Mockery::mock();
        $razorpayMock->utility = $utilityMock;
        $razorpayMock->payment = $paymentServiceMock;

        // Mock Signature Verification
        $utilityMock->shouldReceive('verifyPaymentSignature')
            ->once()
            ->andReturn(true);

        // Mock Payment Fetch
        $paymentDetails = [
            'id' => 'pay_inr_123',
            'status' => 'captured',
            'amount' => 800000, // 100 USD * 80 INR = 8000 INR = 800000 paise
            'currency' => 'INR',
            'method' => 'card',
            'card' => (object) [
                'network' => 'Visa',
                'last4' => '1234',
                'type' => 'debit',
            ],
            'created_at' => time(),
        ];

        // Need to wrap response in ArrayAccess object because RazorpayPayment mapper expects object/array access
        // Actually, Razorpay SDK returns an Entity which implements ArrayAccess. array is fine.

        $paymentServiceMock->shouldReceive('fetch')
            ->with('pay_inr_123')
            ->once()
            ->andReturn(new \ArrayObject($paymentDetails, \ArrayObject::ARRAY_AS_PROPS));

        $this->mockRazorpayClient($razorpayMock);

        // 3. Call confirmPayment
        $methodId = PaymentMethod::where('provider', 'razorpay')->value('id');
        $response = $this->postJson(route('payment.confirm', ['provider' => $methodId]), [
            'token' => $this->order->id,
            'payment_id' => 'pay_inr_123',
            'order_id' => 'order_rzp_123',
            'signature' => 'sig_123',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'success',
        ]);

        // 4. Verify Payment Record in DB
        // Should be stored in BASE currency (USD), which is 100.00
        $this->assertDatabaseHas('payments', [
            'paymentable_id' => $this->order->id,
            'paymentable_type' => $this->order->getMorphClass(),
            'amount' => 100.00, // EXPECTED: 100.00 (Base)
            'currency' => 'USD', // EXPECTED: USD (Base)
        ]);

        // 5. Verify Metadata
        $payment = $this->order->payments()->latest()->first();
        $metadata = $payment->metadata;

        // Gateway amount should be in INR (8000.00)
        $this->assertEquals(8000.00, $metadata['gateway_amount']);
        $this->assertEquals('INR', $metadata['gateway_currency']);
        $this->assertEquals('pay_inr_123', $payment->transaction_id);
    }

    #[Test]
    public function it_stores_payment_in_base_currency_for_all_other_mappers()
    {
        // 1. Set User Currency to GBP (Supported)
        ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.8]);

        // Mock Location to force GBP resolution (GB)
        $position = new Position;
        $position->countryCode = 'GB';
        Location::shouldReceive('get')
            ->andReturn($position);

        // Ensure Currency service is updated for this scope (mocking middleware effect)
        Currency::resolve(['country_code' => 'GB']);

        // Update Order with Billing Address
        $this->order->update([
            'billing_address' => [
                'country_code' => 'GB',
                'line1' => 'Test Street',
            ],
        ]);

        // Payable Setup
        // Grand Total: 100 USD (Base)
        // Gateway Amount: 80 GBP (Foreign)
        $payable = Payable::fromOrder($this->order);

        // Assert Payable Setup
        $this->assertEquals(100.00, $payable->getGrandTotal());
        $this->assertEquals(80.00, $payable->getGatewayAmount());
        $this->assertEquals('GBP', $payable->getCurrency());

        // Mappers to Test
        $mappers = [
            FlutterwavePayment::class => ['id' => 'flw_123', 'status' => 'successful', 'amount' => 80.00, 'currency' => 'GBP'],
            KlarnaPayment::class => ['session_id' => 'klarna_123', 'status' => 'complete', 'order_amount' => 8000, 'purchase_currency' => 'GBP'],
            ManualPayment::class => ['transaction_id' => 'man_123', 'status' => Payment::STATUS_COMPLETED, 'amount' => 80.00, 'currency' => 'GBP'],
            MercadoPagoPayment::class => ['id' => 'mp_123', 'status' => 'approved', 'transaction_amount' => 80.00, 'currency_id' => 'GBP'],
            PaystackPayment::class => ['reference' => 'paystack_123', 'status' => 'success', 'amount' => 8000, 'currency' => 'GBP'],
            XenditPayment::class => ['id' => 'xendit_123', 'status' => 'PAID', 'amount' => 80.00, 'currency' => 'GBP'],
        ];

        foreach ($mappers as $mapperClass => $mockResponse) {
            // Instantiate Mapper
            $paymentMethod = PaymentMethod::factory()->create([
                'provider' => 'flutterwave',
                'credentials' => [
                    ['key' => 'CLIENT_ID', 'value' => 'test_id'],
                    ['key' => 'CLIENT_SECRET', 'value' => 'test_secret'],
                    ['key' => 'ENCRYPTION_KEY', 'value' => 'test_key'],
                ],
            ]);

            // Mappers do not take Payable as argument, and they store Foreign Currency
            $mapper = new $mapperClass($mockResponse, $paymentMethod);

            // Assertions
            // 1. Amount should be in FOREIGN currency (80.00)
            $this->assertEquals(80.00, $mapper->getAmount(), "Failed for $mapperClass: Amount mismatch");

            // 2. Currency should be FOREIGN currency (GBP)
            $this->assertEquals('GBP', $mapper->getCurrency(), "Failed for $mapperClass: Currency mismatch");

            // 3. Metadata Gateway Amount should be in FOREIGN currency (80.00)
            $this->assertEquals(80.00, $mapper->toArray()['metadata']['gateway_amount'], "Failed for $mapperClass: Gateway Amount mismatch");

            // 4. Metadata Gateway Currency should be FOREIGN currency (GBP)
            $this->assertEquals('GBP', $mapper->toArray()['metadata']['gateway_currency'], "Failed for $mapperClass: Gateway Currency mismatch");
        }
    }
}
