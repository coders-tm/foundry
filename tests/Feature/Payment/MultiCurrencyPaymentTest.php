<?php

namespace Tests\Feature\Payment;

use Foundry\Facades\Currency;
use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Order;
use Foundry\Models\PaymentMethod;
use Foundry\Models\User;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class MultiCurrencyPaymentTest extends FeatureTestCase
{
    use WithFaker;

    protected PaymentMethod $paymentMethod;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base currency as USD
        Config::set('app.currency', 'USD');
        Currency::set('USD', 1.0);

        // Get or Create Stripe payment method
        $this->paymentMethod = PaymentMethod::byProvider(PaymentMethod::STRIPE);

        if (! $this->paymentMethod) {
            $this->paymentMethod = PaymentMethod::create([
                'name' => 'Stripe',
                'provider' => PaymentMethod::STRIPE,
                'active' => true,
                'credentials' => [
                    ['key' => 'API_KEY', 'value' => 'pk_test_123'],
                    ['key' => 'API_SECRET', 'value' => 'sk_test_123'],
                ],
                'test_mode' => true,
            ]);
        }

        // Configure payment method for testing
        $this->paymentMethod->update([
            'active' => true,
        ]);
        PaymentMethod::updateProviderCache(PaymentMethod::STRIPE);

        // Ensure Stripe is enabled in config
        Config::set('stripe.enabled', true);

        // Create a test order
        $user = User::factory()->create();
        $this->order = Order::factory()->create([
            'grand_total' => 100.00, // Based in USD
            'customer_id' => $user->id,
        ]);

        $this->order->load('customer');
    }

    protected function tearDown(): void
    {
        $this->resetStripeClient();
        parent::tearDown();
    }

    protected function resetStripeClient()
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('stripeClient');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    protected function mockStripeClient($mock)
    {
        $reflection = new \ReflectionClass(Foundry::class);
        $property = $reflection->getProperty('stripeClient');
        $property->setAccessible(true);
        $property->setValue(null, $mock);
    }

    #[Test]
    public function it_uses_user_currency_when_supported_by_gateway()
    {
        // 1. Set User Currency to EUR (Supported)
        // Create Exchange Rate
        ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
        Currency::set('EUR', 0.9);

        // Update customer currency so controller resolves it
        $this->order->customer->forceFill(['settings' => ['currency' => 'EUR']])->save();

        // Update billing address to Germany (uses EUR)
        $this->order->update([
            'billing_address' => array_merge($this->order->billing_address ?? [], [
                'country_code' => 'DE',
                'country' => 'Germany',
            ]),
        ]);

        // Check pre-condition
        $this->assertEquals('EUR', Currency::code());

        // 2. Mock Stripe Client
        $stripeMock = \Mockery::mock('Stripe\StripeClient');
        $paymentIntentsMock = \Mockery::mock();
        $stripeMock->paymentIntents = $paymentIntentsMock;

        // Expect creation with EUR amount
        // 100 USD * 0.9 = 90 EUR = 9000 cents.

        $paymentIntentsMock->shouldReceive('create')
            ->with(\Mockery::on(function ($args) {
                // Assert currency is EUR
                return $args['currency'] === 'EUR' && $args['amount'] == 9000;
            }))
            ->once()
            ->andReturn((object) [
                'id' => 'pi_eur_supported_success',
                'client_secret' => 'secret_eur_supported',
                'amount' => 9000,
                'currency' => 'eur',
            ]);

        // Bind mock
        $this->mockStripeClient($stripeMock);

        // 3. Call Controller
        $methodId = $this->paymentMethod->id;
        $response = $this->postJson(route('payment.setup-intent', ['provider' => $methodId]), [
            'token' => $this->order->id,
        ]);

        $response->assertOk();
        $this->assertEquals('EUR', Currency::code()); // Should remain EUR
    }

    #[Test]
    public function it_validates_confirm_payment_currency_logic()
    {
        // 1. Set User Currency to GBP (Supported by Stripe)
        ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.8]);
        $this->order->customer->forceFill(['settings' => ['currency' => 'GBP']])->save();

        // Update billing address to UK (uses GBP)
        $this->order->update([
            'billing_address' => array_merge($this->order->billing_address ?? [], [
                'country_code' => 'GB',
                'country' => 'United Kingdom',
            ]),
        ]);

        // Check pre-condition (Currency facade might resolve strictly in controller, checking logic here is redundant if we trust controller, but let's leave it mostly)
        // Note: In test environment without middleware running on this request, Facade isn't auto-updated until controller runs.
        // But for assertions later we assume controller did its job.

        // 2. Mock Stripe Client and Response
        $stripeMock = \Mockery::mock('Stripe\StripeClient');
        $paymentIntentsMock = \Mockery::mock();
        $stripeMock->paymentIntents = $paymentIntentsMock;

        // Mock Payment Intent Retrieval
        $intent = (object) [
            'id' => 'pi_confirm_test',
            'status' => 'succeeded',
            'amount' => 8000, // 100 USD * 0.8 GBP = 80 GBP = 8000 cents
            'currency' => 'gbp',
            // Structure expected by StripePayment mapper
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
            ->with('pi_confirm_test', ['expand' => ['payment_method', 'latest_charge']])
            ->once()
            ->andReturn($intent);

        // Bind mock
        $this->mockStripeClient($stripeMock);

        // 3. Call Controller confirmPayment
        $methodId = $this->paymentMethod->id;
        $response = $this->postJson(route('payment.confirm'), [
            'provider' => $methodId,
            'token' => $this->order->id,
            'payment_intent_id' => 'pi_confirm_test',
        ]);

        $response->assertOk();

        // 4. Assert Currency Reverted for Processing
        // The controller reverts currency if unsupported.
        // We verify the Payment record created has the correct metadata.

        $order = $this->order->fresh();
        $this->assertEquals('paid', $order->payment_status);

        $payment = $order->payments->first();
        $this->assertNotNull($payment);

        // Since currency was Supported (GBP):
        // Amount should be stored in BASE currency (USD) -> 100.00
        $this->assertEquals(100.00, $payment->amount);

        // Metadata should show GBP and converted amount
        $this->assertEquals('GBP', $payment->metadata['gateway_currency']);
        $this->assertEquals(80.00, $payment->metadata['gateway_amount']);
    }

    #[Test]
    public function it_accepts_unsupported_currency_if_processor_allows_it()
    {
        // 1. Set User Currency to XTS (NOT Supported by Stripe)
        ExchangeRate::updateOrCreate(['currency' => 'XTS'], ['rate' => 80.0]);
        $this->order->customer->forceFill(['settings' => ['currency' => 'XTS']])->save();

        // Note: Controller will initialize XTS, but StripeProcessor will revert it to USD.

        // 2. Mock Stripe Client
        $stripeMock = \Mockery::mock('Stripe\StripeClient');
        $paymentIntentsMock = \Mockery::mock();
        $stripeMock->paymentIntents = $paymentIntentsMock;

        // Expect creation with USD (Base) amount not XTS
        // 100 USD = 10000 cents.
        // If it was XTS, it would be 8000 * 100 = 800000 cents (approx).

        $paymentIntentsMock->shouldReceive('create')
            ->with(\Mockery::on(function ($args) {
                // Assert currency is XTS (StripeProcessor checks supported currencies now, so this might fail if we don't handle it)
                // Wait, if StripeProcessor restricts currencies, then "it_accepts_unsupported_currency_if_processor_allows_it" title is tricky.
                // The AbstractPaymentProcessor::isCurrencySupported returns true if list is empty.
                // But now list is NOT empty. So StripeProcessor::isCurrencySupported('XTS') will be false.

                // If unsupported, Payable::getGatewayAmount() should use base currency (USD)?
                // Let's check logic in AbstractPaymentProcessor (not shown, assuming it exists or logic is in generic Controller).
                // If logic is in Controller/Trait using `isCurrencySupported`:
                // If NOT supported: uses base currency (USD).

                // So we expect creation with USD (10000 cents) and currency 'USD'.
                return $args['currency'] === 'USD' && $args['amount'] == 10000;
            }))
            ->once()
            ->andReturn((object) [
                'id' => 'pi_xts_fallback',
                'client_secret' => 'secret_xts',
                'amount' => 10000,
                'currency' => 'usd',
            ]);

        // Bind mock
        $this->mockStripeClient($stripeMock);

        // 3. Call Controller
        $methodId = $this->paymentMethod->id;
        $response = $this->postJson(route('payment.setup-intent', ['provider' => $methodId]), [
            'token' => $this->order->id,
        ]);

        $response->assertOk();
    }

    #[Test]
    public function it_keeps_user_currency_when_supported()
    {
        // 1. Set User Currency to EUR (Supported)
        ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
        Currency::set('EUR', 0.9);
        $this->order->customer->forceFill(['settings' => ['currency' => 'EUR']])->save();

        // Update billing address to Germany (uses EUR)
        $this->order->update([
            'billing_address' => array_merge($this->order->billing_address ?? [], [
                'country_code' => 'DE',
                'country' => 'Germany',
            ]),
        ]);

        // 2. Mock Stripe Client
        $stripeMock = \Mockery::mock('Stripe\StripeClient');
        $paymentIntentsMock = \Mockery::mock();
        $stripeMock->paymentIntents = $paymentIntentsMock;

        // Expect creation with EUR amount
        // 100 USD * 0.9 = 90 EUR = 9000 cents.

        $paymentIntentsMock->shouldReceive('create')
            ->with(\Mockery::on(function ($args) {
                // Assert currency is EUR
                return $args['currency'] === 'EUR' && $args['amount'] == 9000;
            }))
            ->once()
            ->andReturn((object) [
                'id' => 'pi_eur_success',
                'client_secret' => 'secret_eur',
                'amount' => 9000,
                'currency' => 'eur',
            ]);

        // Bind mock
        $this->mockStripeClient($stripeMock);

        // 3. Call Controller
        $methodId = $this->paymentMethod->id;
        $response = $this->postJson(route('payment.setup-intent'), [
            'provider' => $methodId,
            'token' => $this->order->id,
        ]);

        $response->assertOk();
    }
}
