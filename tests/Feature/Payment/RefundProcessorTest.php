<?php

use Foundry\Enum\PaymentStatus;
use Foundry\Exceptions\RefundException;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\User;
use Foundry\Payment\Processor;
use Foundry\Payment\RefundResult;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->order = Order::factory()->create([
        'customer_id' => $this->user->id, 'grand_total' => 100.00, 'paid_total' => 100.00, 'payment_status' => PaymentStatus::PAID,
    ]);

    $this->createPayment = function (string $provider = PaymentProvider::STRIPE, float $amount = 100.00): Payment {
        return $this->order->payments()->create([
            'amount' => $amount, 'status' => PaymentStatus::COMPLETED, 'provider' => $provider, 'transaction_id' => 'txn_'.uniqid(), 'processed_at' => now(),
        ]);
    };
});

it('refund result can create success', function () {
    $result = RefundResult::success(refundId: 'refund_123', amount: 50.00, status: 'succeeded', metadata: ['gateway' => 'stripe']);
    expect($result->isSuccess())->toBeTrue();
    expect($result->getRefundId())->toBe('refund_123');
    expect($result->getAmount())->toEqual(50.00);
    expect($result->getStatus())->toBe('succeeded');
    expect($result->getMetadata())->toBe(['gateway' => 'stripe']);
});

it('refund result to array', function () {
    $result = RefundResult::success(refundId: 'refund_456', amount: 75.00, status: 'completed');
    $array = $result->toArray();
    expect($array['success'])->toBeTrue();
    expect($array['refund_id'])->toBe('refund_456');
    expect($array['amount'])->toEqual(75.00);
    expect($array['status'])->toBe('completed');
});

it('refund result failed throws exception', function () {
    expect(fn () => RefundResult::failed('Refund failed: insufficient balance'))->toThrow(RefundException::class, 'Refund failed: insufficient balance');
});

it('refund result not supported throws exception', function () {
    expect(fn () => RefundResult::notSupported())->toThrow(RefundException::class, 'Refund not supported for this payment method');
});

it('refund exception identifies not supported type', function () {
    try {
        RefundResult::notSupported('Custom reason');
    } catch (RefundException $e) {
        expect($e->isNotSupported())->toBeTrue();
        expect($e->getMessage())->toBe('Custom reason');

        return;
    }
    $this->fail('Expected RefundException was not thrown');
});

it('stripe processor supports refund', function () {
    $processor = Processor::make(PaymentProvider::STRIPE);
    expect($processor->supportsRefund())->toBeTrue();
});

it('paypal processor supports refund', function () {
    $processor = Processor::make(PaymentProvider::PAYPAL);
    expect($processor->supportsRefund())->toBeTrue();
});

it('razorpay processor supports refund', function () {
    $processor = Processor::make(PaymentProvider::RAZORPAY);
    expect($processor->supportsRefund())->toBeTrue();
});

it('flutterwave processor supports refund', function () {
    $processor = Processor::make(PaymentProvider::FLUTTERWAVE);
    expect($processor->supportsRefund())->toBeTrue();
});

it('wallet processor does not support refund', function () {
    $processor = Processor::make(PaymentProvider::WALLET);
    expect($processor->supportsRefund())->toBeFalse();
});

it('manual processor does not support refund', function () {
    $processor = Processor::make(PaymentProvider::MANUAL);
    expect($processor->supportsRefund())->toBeFalse();
});

it('wallet processor throws on refund', function () {
    $processor = Processor::make(PaymentProvider::WALLET);
    $payment = ($this->createPayment)(PaymentProvider::WALLET);
    expect(fn () => $processor->refund($payment, 50.00))->toThrow(RefundException::class, 'Wallet payments cannot be refunded');
});

it('manual processor throws on refund', function () {
    $processor = Processor::make(PaymentProvider::MANUAL);
    $payment = ($this->createPayment)(PaymentProvider::MANUAL);
    expect(fn () => $processor->refund($payment, 50.00))->toThrow(RefundException::class, 'not supported');
});

it('payment calculates refundable amount', function () {
    $payment = ($this->createPayment)('stripe', 100.00);
    expect($payment->refundable_amount)->toEqual(100.00);
    $payment->processRefund();
    $payment->refresh();
    expect($payment->refundable_amount)->toEqual(0);
});

it('payment process refund updates status', function () {
    $payment = ($this->createPayment)('stripe', 100.00);
    $payment->processRefund(40.00, 'Refund request');
    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::REFUNDED);
    expect($payment->refund_amount)->toEqual(100.00);
    expect($payment->isRefunded())->toBeTrue();
});

it('payment is refunded check', function () {
    $payment = ($this->createPayment)('stripe', 100.00);
    expect($payment->isRefunded())->toBeFalse();
    $payment->update(['status' => PaymentStatus::REFUNDED]);
    $payment->refresh();
    expect($payment->isRefunded())->toBeTrue();
});

it('order refund creates refund record', function () {
    $payment = ($this->createPayment)('stripe');
    $refund = $this->order->refundToWallet('Test refund');
    $this->assertDatabaseHas('refunds', ['order_id' => $this->order->id, 'amount' => 100.00, 'to_wallet' => true, 'reason' => 'Test refund']);
});

it('order refund updates order totals', function () {
    $payment = ($this->createPayment)('stripe');
    $originalRefundTotal = $this->order->refund_total;
    $this->order->refundToWallet('Test refund');
    $this->order->refresh();
    expect($this->order->refund_total)->toEqual($originalRefundTotal + 100.00);
});

it('order multiple refunds throws exception', function () {
    $payment = ($this->createPayment)('stripe');
    $this->order->refundToWallet('First refund');
    $this->order->refresh();
    expect(fn () => $this->order->refundToWallet('Second refund'))->toThrow(Exception::class, 'Refund amount must be greater than zero');
});
