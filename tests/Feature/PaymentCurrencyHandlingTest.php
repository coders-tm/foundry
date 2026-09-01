<?php

use Foundry\Models\ExchangeRate;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\User;
use Foundry\Payment\Payable;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(FeatureTestCase::class)->use(RefreshDatabase::class);

beforeEach(function () {
    Config::set('app.currency', 'USD');
});

it('stores correct gateway amount and currency in payment metadata', function () {
    ExchangeRate::updateOrCreate(
        ['currency' => 'EUR'],
        ['rate' => 0.85]
    );

    $user = User::factory()->create();

    $order = Order::factory()->create([
        'customer_id' => $user->id,
        'grand_total' => 100.00,
        'billing_address' => [
            'country' => 'Germany',
            'country_code' => 'DE',
        ],
    ]);

    $payable = Payable::fromOrder($order);

    $this->assertEquals('EUR', $payable->getCurrency());
    $this->assertEquals(85.00, $payable->getGatewayAmount());

    $payment = Payment::createForOrder($order, [
        'provider' => PaymentProvider::STRIPE,
        'transaction_id' => 'tx_123456',
        'amount' => $payable->getGrandTotal(),
        'status' => 'completed',
        'metadata' => [
            'gateway_amount' => $payable->getGatewayAmount(),
            'gateway_currency' => $payable->getCurrency(),
        ],
    ]);

    $this->assertEquals(100.00, $payment->amount, 'Payment amount should be in Base Currency');
    $this->assertEquals(85.00, $payment->metadata['gateway_amount'], 'Metadata should store Gateway Amount');

    $order->refresh();
    $this->assertEquals(100.00, $order->paid_total);
});
