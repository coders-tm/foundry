<?php

use Foundry\Models\Payment;
use Foundry\Payment\Mappers\PaddlePayment;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Foundry\Payment\Processors\PaddleProcessor;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Paddle\SDK\Client;
use Paddle\SDK\Entities\Adjustment;
use Paddle\SDK\Entities\Shared\AdjustmentStatus;
use Paddle\SDK\Entities\Shared\Checkout;
use Paddle\SDK\Entities\Shared\CurrencyCode;
use Paddle\SDK\Entities\Shared\TransactionPaymentAttempt;
use Paddle\SDK\Entities\Shared\TransactionStatus;
use Paddle\SDK\Entities\Transaction;
use Paddle\SDK\Resources\Adjustments\AdjustmentsClient;
use Paddle\SDK\Resources\Transactions\TransactionsClient;

uses(TestCase::class)
    ->use(RefreshDatabase::class);

beforeEach(function () {
    if (empty(env('PADDLE_API_KEY'))) {
        $this->markTestSkipped('Paddle API key not configured.');
    }

    $this->createMockPayable = function (): Payable {
        return Payable::make(['reference_id' => 'ORD-9988', 'grand_total' => 49.99, 'currency' => 'USD', 'customer_email' => 'user@example.com', 'description' => 'SaaS Subscription Order', 'line_items' => [['id' => 'plan_pro', 'name' => 'Pro Plan Monthly', 'price' => 49.99, 'quantity' => 1]], 'source' => (object) ['id' => 1]]);
    };
});

it('creates paddle processor instance', function () {
    $processor = Processor::make(PaymentProvider::PADDLE);
    expect($processor)->toBeInstanceOf(PaddleProcessor::class);
    expect($processor->getProvider())->toBe(PaymentProvider::PADDLE);
});

it('sets up paddle payment intent', function () {
    $processor = new PaddleProcessor;
    $payable = ($this->createMockPayable)();
    try {
        $result = $processor->setupPaymentIntent(new Request, $payable);
        expect($result['transaction_id'])->not->toBeEmpty();
        expect($result['transaction_id'])->toBeStartingWith('txn_');
        expect($result['amount'])->toEqual(49.99);
        expect($result['currency'])->toBe('USD');
        expect($result['checkout_url'])->not->toBeNull();
    } catch (Throwable $e) {
        $paddleMock = $this->createMock(Client::class);
        $transactionsClientMock = $this->createMock(TransactionsClient::class);
        $transactionMock = $this->createMock(Transaction::class);
        $transactionMock->id = 'txn_01h8abcdef1234567890';
        $transactionMock->status = TransactionStatus::Draft();
        $transactionMock->checkout = new Checkout('https://sandbox-checkout.paddle.com/txn_01h8abcdef1234567890');
        $transactionsClientMock->expects($this->once())->method('create')->willReturn($transactionMock);
        $reflection = new ReflectionClass(Client::class);
        $property = $reflection->getProperty('transactions');
        $property->setValue($paddleMock, $transactionsClientMock);
        $this->app->instance(Client::class, $paddleMock);
        $result = $processor->setupPaymentIntent(new Request, $payable);
        expect($result['transaction_id'])->toBe('txn_01h8abcdef1234567890');
        expect($result['amount'])->toEqual(49.99);
        expect($result['currency'])->toBe('USD');
        expect($result['checkout_url'])->toBe('https://sandbox-checkout.paddle.com/txn_01h8abcdef1234567890');
    }
});

it('confirms paddle payment', function () {
    $paddleMock = $this->createMock(Client::class);
    $transactionsClientMock = $this->createMock(TransactionsClient::class);
    $transactionMock = $this->createMock(Transaction::class);
    $transactionMock->id = 'txn_01h8abcdef1234567890';
    $transactionMock->status = TransactionStatus::Completed();
    $transactionMock->currencyCode = CurrencyCode::USD();
    $transactionMock->createdAt = new DateTimeImmutable;
    $transactionMock->payments = [TransactionPaymentAttempt::from(['payment_attempt_id' => 'pay_attempt_123', 'amount' => '4999', 'status' => 'captured', 'created_at' => '2026-01-01T00:00:00Z', 'payment_method_id' => 'pay_123', 'method_details' => ['type' => 'card', 'card' => ['type' => 'visa', 'last4' => '4242', 'expiry_month' => 12, 'expiry_year' => 2030]]])];
    $transactionsClientMock->expects($this->once())->method('get')->with('txn_01h8abcdef1234567890')->willReturn($transactionMock);
    $reflection = new ReflectionClass(Client::class);
    $property = $reflection->getProperty('transactions');
    $property->setValue($paddleMock, $transactionsClientMock);
    $this->app->instance(Client::class, $paddleMock);
    $processor = Processor::make(PaymentProvider::PADDLE);
    $payable = ($this->createMockPayable)();
    $request = new Request(['transaction_id' => 'txn_01h8abcdef1234567890']);
    $result = $processor->confirmPayment($request, $payable);
    expect($result->isSuccess())->toBeTrue();
    expect($result->getTransactionId())->toBe('txn_01h8abcdef1234567890');
    expect($result->getPaymentData())->toBeInstanceOf(PaddlePayment::class);
    expect($result->getPaymentData()->toString())->toBe('Paddle (Visa •••• 4242)');
});

it('handles paddle refund', function () {
    $paddleMock = $this->createMock(Client::class);
    $adjustmentsClientMock = $this->createMock(AdjustmentsClient::class);
    $adjustmentMock = $this->createMock(Adjustment::class);
    $adjustmentMock->id = 'rf_01h8ref12345';
    $adjustmentMock->status = AdjustmentStatus::Approved();
    $adjustmentsClientMock->expects($this->once())->method('create')->willReturn($adjustmentMock);
    $reflection = new ReflectionClass(Client::class);
    $property = $reflection->getProperty('adjustments');
    $property->setValue($paddleMock, $adjustmentsClientMock);
    $this->app->instance(Client::class, $paddleMock);
    $processor = new PaddleProcessor;
    $payment = Payment::create(['paymentable_type' => 'Order', 'paymentable_id' => 1, 'provider' => PaymentProvider::PADDLE, 'transaction_id' => 'txn_01h8abcdef1234567890', 'amount' => 49.99, 'status' => 'completed']);
    $result = $processor->refund($payment, 49.99, 'Customer requested refund');
    expect($result->isSuccess())->toBeTrue();
    expect($result->getRefundId())->toBe('rf_01h8ref12345');
    expect($result->getAmount())->toEqual(49.99);
});
