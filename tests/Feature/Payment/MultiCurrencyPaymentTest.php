<?php

use Foundry\Enum\PaymentStatus;
use Foundry\Facades\Currency;
use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Order;
use Foundry\Models\User;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Facades\Config;

uses(FeatureTestCase::class);

beforeEach(function () {
    Config::set('app.currency', 'USD');
    Currency::set('USD', 1.0);
    Config::set('foundry.payment-providers.stripe.enabled', true);
    $user = User::factory()->create();
    $this->order = Order::factory()->create(['grand_total' => 100.00, 'customer_id' => $user->id]);
    $this->order->load('customer');
});

afterEach(function () {
    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('stripeClient');
    $property->setAccessible(true);
    $property->setValue(null, null);
});

$mockStripeClient = function ($mock) {
    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('stripeClient');
    $property->setAccessible(true);
    $property->setValue(null, $mock);
};

it('uses user currency when supported by gateway', function () use ($mockStripeClient) {
    ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
    Currency::set('EUR', 0.9);
    $this->order->customer->forceFill(['settings' => ['currency' => 'EUR']])->save();
    $this->order->update(['billing_address' => array_merge($this->order->billing_address ?? [], ['country_code' => 'DE', 'country' => 'Germany'])]);
    expect(Currency::code())->toBe('EUR');
    $stripeMock = Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $paymentIntentsMock->shouldReceive('create')->with(Mockery::on(function ($args) {
        return $args['currency'] === 'EUR' && $args['amount'] == 9000;
    }))->once()->andReturn((object) ['id' => 'pi_eur_supported_success', 'client_secret' => 'secret_eur_supported', 'amount' => 9000, 'currency' => 'eur']);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.setup-intent'), ['provider' => PaymentProvider::STRIPE, 'token' => $this->order->id]);
    $response->assertOk();
    expect(Currency::code())->toBe('EUR');
});

it('validates confirm payment currency logic', function () use ($mockStripeClient) {
    ExchangeRate::updateOrCreate(['currency' => 'GBP'], ['rate' => 0.8]);
    $this->order->customer->forceFill(['settings' => ['currency' => 'GBP']])->save();
    $this->order->update(['billing_address' => array_merge($this->order->billing_address ?? [], ['country_code' => 'GB', 'country' => 'United Kingdom'])]);
    $stripeMock = Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $intent = (object) ['id' => 'pi_confirm_test', 'status' => 'succeeded', 'amount' => 8000, 'currency' => 'gbp', 'charges' => (object) ['data' => [(object) ['payment_method_details' => (object) ['type' => 'card', 'card' => (object) ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]]]]]];
    $paymentIntentsMock->shouldReceive('retrieve')->with('pi_confirm_test', ['expand' => ['payment_method', 'latest_charge']])->once()->andReturn($intent);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.confirm'), ['provider' => PaymentProvider::STRIPE, 'token' => $this->order->id, 'payment_intent_id' => 'pi_confirm_test']);
    $response->assertOk();
    $order = $this->order->fresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID);
    $payment = $order->payments->first();
    expect($payment)->not->toBeNull();
    expect($payment->amount)->toEqual(100.00);
    expect($payment->metadata['gateway_currency'])->toBe('GBP');
    expect($payment->metadata['gateway_amount'])->toEqual(80.00);
});

it('accepts unsupported currency if processor allows it', function () use ($mockStripeClient) {
    ExchangeRate::updateOrCreate(['currency' => 'XTS'], ['rate' => 80.0]);
    $this->order->customer->forceFill(['settings' => ['currency' => 'XTS']])->save();
    $stripeMock = Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $paymentIntentsMock->shouldReceive('create')->with(Mockery::on(function ($args) {
        return $args['currency'] === 'USD' && $args['amount'] == 10000;
    }))->once()->andReturn((object) ['id' => 'pi_xts_fallback', 'client_secret' => 'secret_xts', 'amount' => 10000, 'currency' => 'usd']);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.setup-intent'), ['provider' => PaymentProvider::STRIPE, 'token' => $this->order->id]);
    $response->assertOk();
});

it('keeps user currency when supported', function () use ($mockStripeClient) {
    ExchangeRate::updateOrCreate(['currency' => 'EUR'], ['rate' => 0.9]);
    Currency::set('EUR', 0.9);
    $this->order->customer->forceFill(['settings' => ['currency' => 'EUR']])->save();
    $this->order->update(['billing_address' => array_merge($this->order->billing_address ?? [], ['country_code' => 'DE', 'country' => 'Germany'])]);
    $stripeMock = Mockery::mock('Stripe\StripeClient');
    $paymentIntentsMock = Mockery::mock();
    $stripeMock->paymentIntents = $paymentIntentsMock;
    $paymentIntentsMock->shouldReceive('create')->with(Mockery::on(function ($args) {
        return $args['currency'] === 'EUR' && $args['amount'] == 9000;
    }))->once()->andReturn((object) ['id' => 'pi_eur_success', 'client_secret' => 'secret_eur', 'amount' => 9000, 'currency' => 'eur']);
    $mockStripeClient($stripeMock);
    $response = $this->postJson(route('payment.setup-intent'), ['provider' => PaymentProvider::STRIPE, 'token' => $this->order->id]);
    $response->assertOk();
});
