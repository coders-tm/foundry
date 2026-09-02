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

    expect($payable->getCurrency())->toBe('EUR');
    expect($payable->getGatewayAmount())->toEqual(85.00);

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

    expect($payment->amount)->toEqual(100.00);
    expect($payment->metadata['gateway_amount'])->toEqual(85.00);

    $order->refresh();
    expect($order->paid_total)->toEqual(100.00);
});
