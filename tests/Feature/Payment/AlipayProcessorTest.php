<?php

use Foundry\Foundry;
use Foundry\Models\ExchangeRate;
use Foundry\Models\Payment;
use Foundry\Payment\Mappers\AlipayPayment;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Foundry\Payment\Processors\AlipayProcessor;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(FeatureTestCase::class)
    ->use(RefreshDatabase::class);

beforeEach(function () {
    ExchangeRate::create(['currency' => 'CNY', 'rate' => 7.0]);
});

afterEach(function () {
    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('alipayClient');
    $property->setAccessible(true);
    $property->setValue(null, null);
});

it('creates alipay processor instance', function () {
    $processor = Processor::make('alipay');
    expect($processor)->toBeInstanceOf(AlipayProcessor::class);
    expect($processor->getProvider())->toBe('alipay');
});

it('sets up alipay payment intent', function () {
    $processor = new AlipayProcessor;
    $payable = Payable::make(['reference_id' => '12345', 'grand_total' => 100.00, 'currency' => 'CNY', 'billing_address' => ['country_code' => 'CN', 'country' => 'China'], 'description' => 'Test Payment', 'source' => (object) ['id' => 1]]);

    $alipayMock = Mockery::mock('Yansongda\Pay\Gateways\Alipay');
    $redirectMock = Mockery::mock('Symfony\Component\HttpFoundation\RedirectResponse');
    $redirectMock->shouldReceive('getTargetUrl')->andReturn('https://alipay.com/pay');
    $alipayMock->shouldReceive('web')->once()->with(Mockery::on(function ($args) {
        return $args['out_trade_no'] === '12345' && $args['total_amount'] === '700.00' && strpos($args['_return_url'], 'state=') !== false;
    }))->andReturn($redirectMock);

    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('alipayClient');
    $property->setAccessible(true);
    $property->setValue(null, $alipayMock);

    $result = $processor->setupPaymentIntent(new Request, $payable);

    expect($result['redirect_url'])->toBe('https://alipay.com/pay');
    expect($result['payment_intent_id'])->toBe('12345');
    expect($result)->toHaveKey('state_id');
    $this->assertDatabaseHas('payments', ['uuid' => $result['state_id'], 'status' => 'pending']);
});

it('confirms alipay payment', function () {
    $processor = Processor::make('alipay');
    $payable = Payable::make(['grand_total' => 100.00]);
    $request = new Request;

    $alipayMock = Mockery::mock('Yansongda\Pay\Gateways\Alipay');
    $response = ['trade_no' => 'alipay_123', 'out_trade_no' => 'order_123', 'total_amount' => '100.00', 'trade_status' => 'TRADE_SUCCESS', 'fund_bill_list' => [['fund_channel' => 'PCREDIT', 'amount' => '100.00']]];
    $alipayMock->shouldReceive('verify')->once()->andReturn($response);

    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('alipayClient');
    $property->setAccessible(true);
    $property->setValue(null, $alipayMock);

    $result = $processor->confirmPayment($request, $payable);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getTransactionId())->toBe('alipay_123');
    expect($result->getPaymentData())->toBeInstanceOf(AlipayPayment::class);
    expect($result->getPaymentData()->toString())->toBe('Ant Credit Pay (Huabei)');
});

it('handles alipay success callback', function () {
    $processor = new AlipayProcessor;
    $payment = Payment::create(['paymentable_type' => 'App\Models\Order', 'paymentable_id' => 1, 'provider' => 'alipay', 'transaction_id' => 'pending_123', 'amount' => 100.00, 'status' => 'pending']);
    $request = new Request(['state' => $payment->uuid]);

    $alipayMock = Mockery::mock('Yansongda\Pay\Gateways\Alipay');
    $alipayMock->shouldReceive('verify')->once()->andReturn(['trade_no' => 'alipay_123', 'out_trade_no' => '12345', 'total_amount' => '100.00', 'trade_status' => 'TRADE_SUCCESS']);

    $reflection = new ReflectionClass(Foundry::class);
    $property = $reflection->getProperty('alipayClient');
    $property->setAccessible(true);
    $property->setValue(null, $alipayMock);

    $result = $processor->handleSuccessCallback($request);

    expect($result->getMessageType())->toBe('success');
    expect($result->getMessage())->toBe('Alipay payment was successful.');
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'transaction_id' => 'alipay_123', 'status' => 'completed']);
});

it('handles alipay cancel callback', function () {
    $processor = new AlipayProcessor;
    $payment = Payment::create(['paymentable_type' => 'App\Models\Order', 'paymentable_id' => 1, 'provider' => 'alipay', 'transaction_id' => 'pending_123', 'amount' => 100.00, 'status' => 'pending']);
    $request = new Request(['state' => $payment->uuid]);

    $result = $processor->handleCancelCallback($request);

    expect($result->getMessageType())->toBe('success');
    expect($result->getMessage())->toBe('Alipay payment was cancelled.');
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
});
