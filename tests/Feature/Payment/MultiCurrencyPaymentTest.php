<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Config::set('app.currency', 'USD');
    \Foundry\Facades\Currency::set('USD', 1.0);
    \Illuminate\Support\Facades\Config::set('foundry.payment_providers.stripe.enabled', true);
    $user = \Foundry\Models\User::factory()->create();
    $this->order = \Foundry\Models\Order::factory()->create(['grand_total' => 100.00, 'customer_id' => $user->id]);
    $this->order->load('customer');
});

afterEach(function () {
    $reflection = new \ReflectionClass(\Foundry\Foundry::class);
    $property = $reflection->getProperty('stripeClient');
    $property->setAccessible(true);
    $property->setValue(null, null);
});

$mockStripeClient = function ($mock) {
    $reflection = new \ReflectionClass(\Foundry\Foundry::class);
    $property = $reflection->getProperty('stripeClient');
    $property->setAccessible(true);
    $property->setValue(null, $mock);
};

it('uses user currency when supported by gateway', function () use ($mockStripeClient) {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
    \Foundry\Facades\Currency::set('EUR', 0.9);
    $this->order->customer->forceFill(['settings' => ['currency' => 'EUR']])->save();
    $this->order->update(['billing_address' => array_merge($this->order->billing_address ?? [], ['country_code' => 'DE', 'country' => 'Germany'])]);
    $this->assertEquals('EUR', \Foundry\Facades\Currency::code());
    $stripeMock = \Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = \Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $paymentIntentsMock->shouldReceive('create')->with(\Mockery::on(function ($args) { return $args['currency'] === 'EUR' && $args['amount'] == 9000; }))->once()->andReturn((object) ['id' => 'pi_eur_supported_success', 'client_secret' => 'secret_eur_supported', 'amount' => 9000, 'currency' => 'eur']);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.setup-intent'), ['provider' => \Foundry\Services\PaymentProvider::STRIPE, 'token' => $this->order->id]);
    $response->assertOk();
    $this->assertEquals('EUR', \Foundry\Facades\Currency::code());
});

it('validates confirm payment currency logic', function () use ($mockStripeClient) {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.8]);
    $this->order->customer->forceFill(['settings' => ['currency' => 'GBP']])->save();
    $this->order->update(['billing_address' => array_merge($this->order->billing_address ?? [], ['country_code' => 'GB', 'country' => 'United Kingdom'])]);
    $stripeMock = \Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = \Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $intent = (object) ['id' => 'pi_confirm_test', 'status' => 'succeeded', 'amount' => 8000, 'currency' => 'gbp', 'charges' => (object) ['data' => [(object) ['payment_method_details' => (object) ['type' => 'card', 'card' => (object) ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]]]]]];
    $paymentIntentsMock->shouldReceive('retrieve')->with('pi_confirm_test', ['expand' => ['payment_method', 'latest_charge']])->once()->andReturn($intent);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.confirm'), ['provider' => \Foundry\Services\PaymentProvider::STRIPE, 'token' => $this->order->id, 'payment_intent_id' => 'pi_confirm_test']);
    $response->assertOk();
    $order = $this->order->fresh();
    $this->assertEquals(\Foundry\Enum\PaymentStatus::PAID, $order->payment_status);
    $payment = $order->payments->first();
    $this->assertNotNull($payment);
    $this->assertEquals(100.00, $payment->amount);
    $this->assertEquals('GBP', $payment->metadata['gateway_currency']);
    $this->assertEquals(80.00, $payment->metadata['gateway_amount']);
});

it('accepts unsupported currency if processor allows it', function () use ($mockStripeClient) {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'XTS'], ['rate' => 80.0]);
    $this->order->customer->forceFill(['settings' => ['currency' => 'XTS']])->save();
    $stripeMock = \Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = \Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $paymentIntentsMock->shouldReceive('create')->with(\Mockery::on(function ($args) { return $args['currency'] === 'USD' && $args['amount'] == 10000; }))->once()->andReturn((object) ['id' => 'pi_xts_fallback', 'client_secret' => 'secret_xts', 'amount' => 10000, 'currency' => 'usd']);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.setup-intent'), ['provider' => \Foundry\Services\PaymentProvider::STRIPE, 'token' => $this->order->id]);
    $response->assertOk();
});

it('keeps user currency when supported', function () use ($mockStripeClient) {
    \Foundry\Models\ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
    \Foundry\Facades\Currency::set('EUR', 0.9);
    $this->order->customer->forceFill(['settings' => ['currency' => 'EUR']])->save();
    $this->order->update(['billing_address' => array_merge($this->order->billing_address ?? [], ['country_code' => 'DE', 'country' => 'Germany'])]);
    $stripeMock = \Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = \Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $paymentIntentsMock->shouldReceive('create')->with(\Mockery::on(function ($args) { return $args['currency'] === 'EUR' && $args['amount'] == 9000; }))->once()->andReturn((object) ['id' => 'pi_eur_success', 'client_secret' => 'secret_eur', 'amount' => 9000, 'currency' => 'eur']);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.setup-intent'), ['provider' => \Foundry\Services\PaymentProvider::STRIPE, 'token' => $this->order->id]);
    $response->assertOk();
});
