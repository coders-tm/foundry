<?php

use Foundry\Facades\Currency;
use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Payment;
use Foundry\Payment\Mappers\FlutterwavePayment;
use Foundry\Payment\Mappers\KlarnaPayment;
use Foundry\Payment\Mappers\ManualPayment;
use Foundry\Payment\Mappers\MercadoPagoPayment;
use Foundry\Payment\Mappers\PaystackPayment;
use Foundry\Payment\Mappers\XenditPayment;
use Foundry\Payment\Payable;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

uses(FeatureTestCase::class)
    ->use(RefreshDatabase::class)
    ->use(WithFaker::class);

beforeEach(function () {
    Config::set('app.currency', 'USD');
    Currency::set('USD', 1.0);
    ExchangeRate::firstOrCreate(['currency' => 'USD'], ['rate' => 1.0]);
    $this->order = Foundry::$orderModel::factory()->create(['grand_total' => 100.00]);
});

afterEach(function () {
    $reflection = new ReflectionClass(Foundry::class);
    foreach (['paypalClient', 'stripeClient', 'razorpayClient'] as $prop) {
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
});

$mockPaypalClient = function ($mock) {
    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('paypalClient');
    $property->setAccessible(true);
    $property->setValue(null, $mock);
};

$mockStripeClient = function ($mock) {
    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('stripeClient');
    $property->setAccessible(true);
    $property->setValue(null, $mock);
};

$mockRazorpayClient = function ($mock) {
    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('razorpayClient');
    $property->setAccessible(true);
    $property->setValue(null, $mock);
};

it('stores payment in base currency when paid in foreign currency with paypal', function () use ($mockPaypalClient) {
    Config::set('foundry.payment-providers.paypal.enabled', true);
    ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
    $position = new Position;
    $position->countryCode = 'DE';
    Location::shouldReceive('get')->andReturn($position);
    Currency::resolve(['country_code' => 'DE']);
    expect(Currency::code())->toBe('EUR');
    $this->order->update(['billing_address' => ['country_code' => 'DE', 'line1' => 'Test Strasse']]);
    $paypalMock = Mockery::mock('stdClass');
    $paypalOrderId = 'ORDER-123';
    $captureResponse = ['id' => $paypalOrderId, 'status' => 'COMPLETED', 'payer' => ['email_address' => 'test@example.com', 'name' => ['given_name' => 'John', 'surname' => 'Doe']], 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-123', 'status' => 'COMPLETED', 'amount' => ['value' => '90.00', 'currency_code' => 'EUR'], 'seller_receivable_breakdown' => ['paypal_fee' => ['value' => '3.00', 'currency_code' => 'EUR']]]]]]]];
    $paypalMock->shouldReceive('capturePaymentOrder')->with($paypalOrderId)->once()->andReturn($captureResponse);
    $mockPaypalClient($paypalMock);
    $response = $this->postJson(route('payment.confirm'), ['provider' => PaymentProvider::PAYPAL, 'token' => $this->order->id, 'paypal_order_id' => $paypalOrderId, 'payer_id' => 'PAYER-123']);
    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'success']);
    $this->assertDatabaseHas('payments', ['paymentable_id' => $this->order->id, 'paymentable_type' => $this->order->getMorphClass(), 'amount' => 100.00, 'currency' => 'USD']);
    $payment = $this->order->payments()->latest()->first();
    $metadata = $payment->metadata;
    expect($metadata['gateway_amount'])->toEqual(90.00);
    expect($metadata['gateway_currency'])->toBe('EUR');
    expect($payment->transaction_id)->toBe('CAP-123');
});

it('stores payment in base currency when paid in foreign currency with stripe', function () use ($mockStripeClient) {
    ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
    $position = new Position;
    $position->countryCode = 'DE';
    Location::shouldReceive('get')->andReturn($position);
    Currency::resolve(['country_code' => 'DE']);
    Config::set('foundry.payment-providers.paypal.enabled', true);
    $this->order->update(['billing_address' => ['country_code' => 'DE', 'line1' => 'Test Strasse']]);
    $stripeMock = Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $intent = (object) ['id' => 'pi_eur_confirm_test', 'status' => 'succeeded', 'amount' => 9000, 'currency' => 'eur', 'charges' => (object) ['data' => [(object) ['payment_method_details' => (object) ['type' => 'card', 'card' => (object) ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]]]]]];
    $paymentIntentsMock->shouldReceive('retrieve')->with('pi_eur_confirm_test', ['expand' => ['payment_method', 'latest_charge']])->once()->andReturn($intent);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.confirm'), ['provider' => PaymentProvider::STRIPE, 'token' => $this->order->id, 'payment_intent_id' => 'pi_eur_confirm_test']);
    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'success']);
    $this->assertDatabaseHas('payments', ['paymentable_id' => $this->order->id, 'paymentable_type' => $this->order->getMorphClass(), 'amount' => 100.00, 'currency' => 'USD']);
    $payment = $this->order->payments()->latest()->first();
    $metadata = $payment->metadata;
    expect($metadata['gateway_amount'])->toEqual(90.00);
    expect($metadata['gateway_currency'])->toBe('EUR');
    expect($payment->transaction_id)->toBe('pi_eur_confirm_test');
});

it('stores payment in base currency when paid in foreign currency with razorpay', function () use ($mockRazorpayClient) {
    ExchangeRate::updateOrCreate(['currency' => 'INR'], ['rate' => 80.0]);
    $position = new Position;
    $position->countryCode = 'IN';
    Location::shouldReceive('get')->andReturn($position);
    Currency::resolve(['country_code' => 'IN']);
    Config::set('foundry.payment-providers.razorpay.enabled', true);
    $this->order->update(['billing_address' => ['country_code' => 'IN', 'line1' => 'Test Road']]);
    $razorpayMock = Mockery::mock('Razorpay\Api\Api');
    $utilityMock = Mockery::mock();
    $paymentServiceMock = Mockery::mock();
    $razorpayMock->utility = $utilityMock;
    $razorpayMock->payment = $paymentServiceMock;
    $utilityMock->shouldReceive('verifyPaymentSignature')->once()->andReturn(true);
    $paymentDetails = ['id' => 'pay_inr_123', 'status' => 'captured', 'amount' => 800000, 'currency' => 'INR', 'method' => 'card', 'card' => (object) ['network' => 'Visa', 'last4' => '1234', 'type' => 'debit'], 'created_at' => time()];
    $paymentServiceMock->shouldReceive('fetch')->with('pay_inr_123')->once()->andReturn(new ArrayObject($paymentDetails, ArrayObject::ARRAY_AS_PROPS));
    $mockRazorpayClient($razorpayMock);
    $response = $this->postJson(route('payment.confirm'), ['provider' => PaymentProvider::RAZORPAY, 'token' => $this->order->id, 'payment_id' => 'pay_inr_123', 'order_id' => 'order_rzp_123', 'signature' => 'sig_123']);
    $response->assertOk();
    $response->assertJson(['success' => true, 'status' => 'success']);
    $this->assertDatabaseHas('payments', ['paymentable_id' => $this->order->id, 'paymentable_type' => $this->order->getMorphClass(), 'amount' => 100.00, 'currency' => 'USD']);
    $payment = $this->order->payments()->latest()->first();
    $metadata = $payment->metadata;
    expect($metadata['gateway_amount'])->toEqual(8000.00);
    expect($metadata['gateway_currency'])->toBe('INR');
    expect($payment->transaction_id)->toBe('pay_inr_123');
});

it('stores payment in base currency for all other mappers', function () {
    ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.8]);
    $position = new Position;
    $position->countryCode = 'GB';
    Location::shouldReceive('get')->andReturn($position);
    Currency::resolve(['country_code' => 'GB']);
    $this->order->update(['billing_address' => ['country_code' => 'GB', 'line1' => 'Test Street']]);
    $payable = Payable::fromOrder($this->order);
    expect($payable->getGrandTotal())->toEqual(100.00);
    expect($payable->getGatewayAmount())->toEqual(80.00);
    expect($payable->getCurrency())->toBe('GBP');
    $mappers = [
        FlutterwavePayment::class => ['id' => 'flw_123', 'status' => 'successful', 'amount' => 80.00, 'currency' => 'GBP'],
        KlarnaPayment::class => ['session_id' => 'klarna_123', 'status' => 'complete', 'order_amount' => 8000, 'purchase_currency' => 'GBP'],
        ManualPayment::class => ['transaction_id' => 'man_123', 'status' => Payment::STATUS_COMPLETED, 'amount' => 80.00, 'currency' => 'GBP'],
        MercadoPagoPayment::class => ['id' => 'mp_123', 'status' => 'approved', 'transaction_amount' => 80.00, 'currency_id' => 'GBP'],
        PaystackPayment::class => ['reference' => 'paystack_123', 'status' => 'success', 'amount' => 8000, 'currency' => 'GBP'],
        XenditPayment::class => ['id' => 'xendit_123', 'status' => 'PAID', 'amount' => 80.00, 'currency' => 'GBP'],
    ];
    foreach ($mappers as $mapperClass => $mockResponse) {
        $mapper = new $mapperClass($mockResponse, 'flutterwave');
        expect($mapper->getAmount())->toEqual(80.00);
        expect($mapper->getCurrency())->toBe('GBP');
        expect($mapper->toArray()['metadata']['gateway_amount'])->toEqual(80.00);
        expect($mapper->toArray()['metadata']['gateway_currency'])->toBe('GBP');
    }
});
