<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class)
    ->use(\Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->use(\Illuminate\Foundation\Testing\WithFaker::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Config::set('app.currency', 'USD');
    \Foundry\Facades\Currency::set('USD', 1.0);
    \Foundry\Models\ExchangeRate::firstOrCreate(['currency' => 'USD'], ['rate' => 1.0]);
    $this->order = \Foundry\Foundry::$orderModel::factory()->create(['grand_total' => 100.00]);
});

afterEach(function () {
    $reflection = new \ReflectionClass(\Foundry\Foundry::class);
    foreach (['paypalClient', 'stripeClient', 'razorpayClient'] as $prop) {
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
});

$mockPaypalClient = function ($mock) {
    $reflection = new \ReflectionClass(\Foundry\Foundry::class);
    $property = $reflection->getProperty('paypalClient');
    $property->setAccessible(true);
    $property->setValue(null, $mock);
};

$mockStripeClient = function ($mock) {
    $reflection = new \ReflectionClass(\Foundry\Foundry::class);
    $property = $reflection->getProperty('stripeClient');
    $property->setAccessible(true);
    $property->setValue(null, $mock);
};

$mockRazorpayClient = function ($mock) {
    $reflection = new \ReflectionClass(\Foundry\Foundry::class);
    $property = $reflection->getProperty('razorpayClient');
    $property->setAccessible(true);
    $property->setValue(null, $mock);
};

it('stores payment in base currency when paid in foreign currency with paypal', function () use ($mockPaypalClient) {
    \Illuminate\Support\Facades\Config::set('foundry.payment_providers.paypal.enabled', true);
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'DE';
    \Stevebauman\Location\Facades\Location::shouldReceive('get')->andReturn($position);
    \Foundry\Facades\Currency::resolve(['country_code' => 'DE']);
    $this->assertEquals('EUR', \Foundry\Facades\Currency::code());
    $this->order->update(['billing_address' => ['country_code' => 'DE', 'line1' => 'Test Strasse']]);
    $paypalMock = \Mockery::mock('stdClass');
    $paypalOrderId = 'ORDER-123';
    $captureResponse = ['id' => $paypalOrderId, 'status' => 'COMPLETED', 'payer' => ['email_address' => 'test@example.com', 'name' => ['given_name' => 'John', 'surname' => 'Doe']], 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-123', 'status' => 'COMPLETED', 'amount' => ['value' => '90.00', 'currency_code' => 'EUR'], 'seller_receivable_breakdown' => ['paypal_fee' => ['value' => '3.00', 'currency_code' => 'EUR']]]]]]]];
    $paypalMock->shouldReceive('capturePaymentOrder')->with($paypalOrderId)->once()->andReturn($captureResponse);
    $mockPaypalClient($paypalMock);
    $response = $this->postJson(route('payment.confirm'), ['provider' => \Foundry\Services\PaymentProvider::PAYPAL, 'token' => $this->order->id, 'paypal_order_id' => $paypalOrderId, 'payer_id' => 'PAYER-123']);
    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'success']);
    $this->assertDatabaseHas('payments', ['paymentable_id' => $this->order->id, 'paymentable_type' => $this->order->getMorphClass(), 'amount' => 100.00, 'currency' => 'USD']);
    $payment = $this->order->payments()->latest()->first();
    $metadata = $payment->metadata;
    $this->assertEquals(90.00, $metadata['gateway_amount']);
    $this->assertEquals('EUR', $metadata['gateway_currency']);
    $this->assertEquals('CAP-123', $payment->transaction_id);
});

it('stores payment in base currency when paid in foreign currency with stripe', function () use ($mockStripeClient) {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'DE';
    \Stevebauman\Location\Facades\Location::shouldReceive('get')->andReturn($position);
    \Foundry\Facades\Currency::resolve(['country_code' => 'DE']);
    \Illuminate\Support\Facades\Config::set('foundry.payment_providers.paypal.enabled', true);
    $this->order->update(['billing_address' => ['country_code' => 'DE', 'line1' => 'Test Strasse']]);
    $stripeMock = \Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = \Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $intent = (object) ['id' => 'pi_eur_confirm_test', 'status' => 'succeeded', 'amount' => 9000, 'currency' => 'eur', 'charges' => (object) ['data' => [(object) ['payment_method_details' => (object) ['type' => 'card', 'card' => (object) ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]]]]]];
    $paymentIntentsMock->shouldReceive('retrieve')->with('pi_eur_confirm_test', ['expand' => ['payment_method', 'latest_charge']])->once()->andReturn($intent);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.confirm'), ['provider' => \Foundry\Services\PaymentProvider::STRIPE, 'token' => $this->order->id, 'payment_intent_id' => 'pi_eur_confirm_test']);
    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'success']);
    $this->assertDatabaseHas('payments', ['paymentable_id' => $this->order->id, 'paymentable_type' => $this->order->getMorphClass(), 'amount' => 100.00, 'currency' => 'USD']);
    $payment = $this->order->payments()->latest()->first();
    $metadata = $payment->metadata;
    $this->assertEquals(90.00, $metadata['gateway_amount']);
    $this->assertEquals('EUR', $metadata['gateway_currency']);
    $this->assertEquals('pi_eur_confirm_test', $payment->transaction_id);
});

it('stores payment in base currency when paid in foreign currency with razorpay', function () use ($mockRazorpayClient) {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'INR'], ['rate' => 80.0]);
    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'IN';
    \Stevebauman\Location\Facades\Location::shouldReceive('get')->andReturn($position);
    \Foundry\Facades\Currency::resolve(['country_code' => 'IN']);
    \Illuminate\Support\Facades\Config::set('foundry.payment_providers.razorpay.enabled', true);
    $this->order->update(['billing_address' => ['country_code' => 'IN', 'line1' => 'Test Road']]);
    $razorpayMock = \Mockery::mock('Razorpay\Api\Api');
    $utilityMock = \Mockery::mock();
    $paymentServiceMock = \Mockery::mock();
    $razorpayMock->utility = $utilityMock;
    $razorpayMock->payment = $paymentServiceMock;
    $utilityMock->shouldReceive('verifyPaymentSignature')->once()->andReturn(true);
    $paymentDetails = ['id' => 'pay_inr_123', 'status' => 'captured', 'amount' => 800000, 'currency' => 'INR', 'method' => 'card', 'card' => (object) ['network' => 'Visa', 'last4' => '1234', 'type' => 'debit'], 'created_at' => time()];
    $paymentServiceMock->shouldReceive('fetch')->with('pay_inr_123')->once()->andReturn(new \ArrayObject($paymentDetails, \ArrayObject::ARRAY_AS_PROPS));
    $mockRazorpayClient($razorpayMock);
    $response = $this->postJson(route('payment.confirm'), ['provider' => \Foundry\Services\PaymentProvider::RAZORPAY, 'token' => $this->order->id, 'payment_id' => 'pay_inr_123', 'order_id' => 'order_rzp_123', 'signature' => 'sig_123']);
    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'success']);
    $this->assertDatabaseHas('payments', ['paymentable_id' => $this->order->id, 'paymentable_type' => $this->order->getMorphClass(), 'amount' => 100.00, 'currency' => 'USD']);
    $payment = $this->order->payments()->latest()->first();
    $metadata = $payment->metadata;
    $this->assertEquals(8000.00, $metadata['gateway_amount']);
    $this->assertEquals('INR', $metadata['gateway_currency']);
    $this->assertEquals('pay_inr_123', $payment->transaction_id);
});

it('stores payment in base currency for all other mappers', function () {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.8]);
    $position = new \Stevebauman\Location\Position;
    $position->countryCode = 'GB';
    \Stevebauman\Location\Facades\Location::shouldReceive('get')->andReturn($position);
    \Foundry\Facades\Currency::resolve(['country_code' => 'GB']);
    $this->order->update(['billing_address' => ['country_code' => 'GB', 'line1' => 'Test Street']]);
    $payable = \Foundry\Payment\Payable::fromOrder($this->order);
    $this->assertEquals(100.00, $payable->getGrandTotal());
    $this->assertEquals(80.00, $payable->getGatewayAmount());
    $this->assertEquals('GBP', $payable->getCurrency());
    $mappers = [
        \Foundry\Payment\Mappers\FlutterwavePayment::class => ['id' => 'flw_123', 'status' => 'successful', 'amount' => 80.00, 'currency' => 'GBP'],
        \Foundry\Payment\Mappers\KlarnaPayment::class => ['session_id' => 'klarna_123', 'status' => 'complete', 'order_amount' => 8000, 'purchase_currency' => 'GBP'],
        \Foundry\Payment\Mappers\ManualPayment::class => ['transaction_id' => 'man_123', 'status' => \Foundry\Models\Payment::STATUS_COMPLETED, 'amount' => 80.00, 'currency' => 'GBP'],
        \Foundry\Payment\Mappers\MercadoPagoPayment::class => ['id' => 'mp_123', 'status' => 'approved', 'transaction_amount' => 80.00, 'currency_id' => 'GBP'],
        \Foundry\Payment\Mappers\PaystackPayment::class => ['reference' => 'paystack_123', 'status' => 'success', 'amount' => 8000, 'currency' => 'GBP'],
        \Foundry\Payment\Mappers\XenditPayment::class => ['id' => 'xendit_123', 'status' => 'PAID', 'amount' => 80.00, 'currency' => 'GBP'],
    ];
    foreach ($mappers as $mapperClass => $mockResponse) {
        $mapper = new $mapperClass($mockResponse, 'flutterwave');
        $this->assertEquals(80.00, $mapper->getAmount(), "Failed for $mapperClass: Amount mismatch");
        $this->assertEquals('GBP', $mapper->getCurrency(), "Failed for $mapperClass: Currency mismatch");
        $this->assertEquals(80.00, $mapper->toArray()['metadata']['gateway_amount'], "Failed for $mapperClass: Gateway Amount mismatch");
        $this->assertEquals('GBP', $mapper->toArray()['metadata']['gateway_currency'], "Failed for $mapperClass: Gateway Currency mismatch");
    }
});
