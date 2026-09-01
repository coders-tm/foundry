<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class)->use(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Config::set('app.currency', 'USD');
});

it('stores correct gateway amount and currency in payment metadata', function () {
    \Foundry\Models\ExchangeRate::updateOrCreate(
        ['currency' => 'EUR'],
        ['rate' => 0.85]
    );

    $user = \Foundry\Models\User::factory()->create();

    $order = \Foundry\Models\Order::factory()->create([
        'customer_id' => $user->id,
        'grand_total' => 100.00,
        'billing_address' => [
            'country' => 'Germany',
            'country_code' => 'DE',
        ],
    ]);

    $payable = \Foundry\Payment\Payable::fromOrder($order);

    $this->assertEquals('EUR', $payable->getCurrency());
    $this->assertEquals(85.00, $payable->getGatewayAmount());

    $payment = \Foundry\Models\Payment::createForOrder($order, [
        'provider' => \Foundry\Services\PaymentProvider::STRIPE,
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
