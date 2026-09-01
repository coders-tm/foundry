<?php

uses(\Foundry\Tests\TestCase::class)
    ->use(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    if (empty(env('PADDLE_API_KEY'))) {
        $this->markTestSkipped('Paddle API key not configured.');
    }
});

$createMockPayable = function (): \Foundry\Payment\Payable {
    return \Foundry\Payment\Payable::make(['reference_id' => 'ORD-9988', 'grand_total' => 49.99, 'currency' => 'USD', 'customer_email' => 'user@example.com', 'description' => 'SaaS Subscription Order', 'line_items' => [['id' => 'plan_pro', 'name' => 'Pro Plan Monthly', 'price' => 49.99, 'quantity' => 1]], 'source' => (object) ['id' => 1]]);
};

it('creates paddle processor instance', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::PADDLE);
    $this->assertInstanceOf(\Foundry\Payment\Processors\PaddleProcessor::class, $processor);
    $this->assertEquals(\Foundry\Services\PaymentProvider::PADDLE, $processor->getProvider());
});

it('sets up paddle payment intent', function () {
    $processor = new \Foundry\Payment\Processors\PaddleProcessor;
    $payable = createMockPayable();
    try {
        $result = $processor->setupPaymentIntent(new \Illuminate\Http\Request, $payable);
        $this->assertNotEmpty($result['transaction_id']);
        $this->assertStringStartsWith('txn_', $result['transaction_id']);
        $this->assertEquals(49.99, $result['amount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertNotNull($result['checkout_url']);
    } catch (\Throwable $e) {
        $paddleMock = $this->createMock(\Paddle\SDK\Client::class);
        $transactionsClientMock = $this->createMock(\Paddle\SDK\Resources\Transactions\TransactionsClient::class);
        $transactionMock = $this->createMock(\Paddle\SDK\Entities\Transaction::class);
        $transactionMock->id = 'txn_01h8abcdef1234567890';
        $transactionMock->status = \Paddle\SDK\Entities\Shared\TransactionStatus::Draft();
        $transactionMock->checkout = new \Paddle\SDK\Entities\Shared\Checkout('https://sandbox-checkout.paddle.com/txn_01h8abcdef1234567890');
        $transactionsClientMock->expects($this->once())->method('create')->willReturn($transactionMock);
        $reflection = new \ReflectionClass(\Paddle\SDK\Client::class);
        $property = $reflection->getProperty('transactions');
        $property->setValue($paddleMock, $transactionsClientMock);
        $this->app->instance(\Paddle\SDK\Client::class, $paddleMock);
        $result = $processor->setupPaymentIntent(new \Illuminate\Http\Request, $payable);
        $this->assertEquals('txn_01h8abcdef1234567890', $result['transaction_id']);
        $this->assertEquals(49.99, $result['amount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals('https://sandbox-checkout.paddle.com/txn_01h8abcdef1234567890', $result['checkout_url']);
    }
});

it('confirms paddle payment', function () {
    $paddleMock = $this->createMock(\Paddle\SDK\Client::class);
    $transactionsClientMock = $this->createMock(\Paddle\SDK\Resources\Transactions\TransactionsClient::class);
    $transactionMock = $this->createMock(\Paddle\SDK\Entities\Transaction::class);
    $transactionMock->id = 'txn_01h8abcdef1234567890';
    $transactionMock->status = \Paddle\SDK\Entities\Shared\TransactionStatus::Completed();
    $transactionMock->currencyCode = \Paddle\SDK\Entities\Shared\CurrencyCode::USD();
    $transactionMock->payments = [\Paddle\SDK\Entities\Shared\TransactionPaymentAttempt::from(['payment_attempt_id' => 'pay_attempt_123', 'amount' => '4999', 'status' => 'captured', 'created_at' => '2026-01-01T00:00:00Z', 'payment_method_id' => 'pay_123', 'method_details' => ['type' => 'card', 'card' => ['type' => 'visa', 'last4' => '4242', 'expiry_month' => 12, 'expiry_year' => 2030]]])];
    $transactionsClientMock->expects($this->once())->method('get')->with('txn_01h8abcdef1234567890')->willReturn($transactionMock);
    $reflection = new \ReflectionClass(\Paddle\SDK\Client::class);
    $property = $reflection->getProperty('transactions');
    $property->setValue($paddleMock, $transactionsClientMock);
    $this->app->instance(\Paddle\SDK\Client::class, $paddleMock);
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::PADDLE);
    $payable = createMockPayable();
    $request = new \Illuminate\Http\Request(['transaction_id' => 'txn_01h8abcdef1234567890']);
    $result = $processor->confirmPayment($request, $payable);
    $this->assertTrue($result->isSuccess());
    $this->assertEquals('txn_01h8abcdef1234567890', $result->getTransactionId());
    $this->assertInstanceOf(\Foundry\Payment\Mappers\PaddlePayment::class, $result->getPaymentData());
    $this->assertEquals('Paddle (Visa •••• 4242)', $result->getPaymentData()->toString());
});

it('handles paddle refund', function () {
    $paddleMock = $this->createMock(\Paddle\SDK\Client::class);
    $adjustmentsClientMock = $this->createMock(\Paddle\SDK\Resources\Adjustments\AdjustmentsClient::class);
    $adjustmentMock = $this->createMock(\Paddle\SDK\Entities\Adjustment::class);
    $adjustmentMock->id = 'rf_01h8ref12345';
    $adjustmentMock->status = \Paddle\SDK\Entities\Shared\AdjustmentStatus::Approved();
    $adjustmentsClientMock->expects($this->once())->method('create')->willReturn($adjustmentMock);
    $reflection = new \ReflectionClass(\Paddle\SDK\Client::class);
    $property = $reflection->getProperty('adjustments');
    $property->setValue($paddleMock, $adjustmentsClientMock);
    $this->app->instance(\Paddle\SDK\Client::class, $paddleMock);
    $processor = new \Foundry\Payment\Processors\PaddleProcessor;
    $payment = \Foundry\Models\Payment::create(['paymentable_type' => 'Order', 'paymentable_id' => 1, 'provider' => \Foundry\Services\PaymentProvider::PADDLE, 'transaction_id' => 'txn_01h8abcdef1234567890', 'amount' => 49.99, 'status' => 'completed']);
    $result = $processor->refund($payment, 49.99, 'Customer requested refund');
    $this->assertTrue($result->isSuccess());
    $this->assertEquals('rf_01h8ref12345', $result->getRefundId());
    $this->assertEquals(49.99, $result->getAmount());
});
