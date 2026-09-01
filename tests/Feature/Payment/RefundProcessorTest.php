<?php

uses(\Foundry\Tests\TestCase::class);

beforeEach(function () {
    $this->user = \Foundry\Models\User::factory()->create();
    $this->order = \Foundry\Models\Order::factory()->create([
        'customer_id' => $this->user->id, 'grand_total' => 100.00, 'paid_total' => 100.00, 'payment_status' => \Foundry\Enum\PaymentStatus::PAID,
    ]);
});

it('refund result can create success', function () {
    $result = \Foundry\Payment\RefundResult::success(refundId: 'refund_123', amount: 50.00, status: 'succeeded', metadata: ['gateway' => 'stripe']);
    $this->assertTrue($result->isSuccess());
    $this->assertEquals('refund_123', $result->getRefundId());
    $this->assertEquals(50.00, $result->getAmount());
    $this->assertEquals('succeeded', $result->getStatus());
    $this->assertEquals(['gateway' => 'stripe'], $result->getMetadata());
});

it('refund result to array', function () {
    $result = \Foundry\Payment\RefundResult::success(refundId: 'refund_456', amount: 75.00, status: 'completed');
    $array = $result->toArray();
    $this->assertTrue($array['success']);
    $this->assertEquals('refund_456', $array['refund_id']);
    $this->assertEquals(75.00, $array['amount']);
    $this->assertEquals('completed', $array['status']);
});

it('refund result failed throws exception', function () {
    $this->expectException(\Foundry\Exceptions\RefundException::class);
    $this->expectExceptionMessage('Refund failed: insufficient balance');
    \Foundry\Payment\RefundResult::failed('Refund failed: insufficient balance');
});

it('refund result not supported throws exception', function () {
    $this->expectException(\Foundry\Exceptions\RefundException::class);
    $this->expectExceptionMessage('Refund not supported for this payment method');
    \Foundry\Payment\RefundResult::notSupported();
});

it('refund exception identifies not supported type', function () {
    try { \Foundry\Payment\RefundResult::notSupported('Custom reason'); } catch (\Foundry\Exceptions\RefundException $e) {
        $this->assertTrue($e->isNotSupported());
        $this->assertEquals('Custom reason', $e->getMessage());
        return;
    }
    $this->fail('Expected RefundException was not thrown');
});

it('stripe processor supports refund', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::STRIPE);
    $this->assertTrue($processor->supportsRefund());
});

it('paypal processor supports refund', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::PAYPAL);
    $this->assertTrue($processor->supportsRefund());
});

it('razorpay processor supports refund', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::RAZORPAY);
    $this->assertTrue($processor->supportsRefund());
});

it('flutterwave processor supports refund', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::FLUTTERWAVE);
    $this->assertTrue($processor->supportsRefund());
});

it('wallet processor does not support refund', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::WALLET);
    $this->assertFalse($processor->supportsRefund());
});

it('manual processor does not support refund', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::MANUAL);
    $this->assertFalse($processor->supportsRefund());
});

it('wallet processor throws on refund', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::WALLET);
    $payment = $this->$createPayment(\Foundry\Services\PaymentProvider::WALLET);
    $this->expectException(\Foundry\Exceptions\RefundException::class);
    $this->expectExceptionMessage('Wallet payments cannot be refunded');
    $processor->refund($payment, 50.00);
});

it('manual processor throws on refund', function () {
    $processor = \Foundry\Payment\Processor::make(\Foundry\Services\PaymentProvider::MANUAL);
    $payment = $this->$createPayment(\Foundry\Services\PaymentProvider::MANUAL);
    $this->expectException(\Foundry\Exceptions\RefundException::class);
    $this->expectExceptionMessage('not supported');
    $processor->refund($payment, 50.00);
});

it('payment calculates refundable amount', function () {
    $payment = $this->$createPayment('stripe', 100.00);
    $this->assertEquals(100.00, $payment->refundable_amount);
    $payment->processRefund();
    $payment->refresh();
    $this->assertEquals(0, $payment->refundable_amount);
});

it('payment process refund updates status', function () {
    $payment = $this->$createPayment('stripe', 100.00);
    $payment->processRefund(40.00, 'Refund request');
    $payment->refresh();
    $this->assertEquals(\Foundry\Enum\PaymentStatus::REFUNDED, $payment->status);
    $this->assertEquals(100.00, $payment->refund_amount);
    $this->assertTrue($payment->isRefunded());
});

it('payment is refunded check', function () {
    $payment = $this->$createPayment('stripe', 100.00);
    $this->assertFalse($payment->isRefunded());
    $payment->update(['status' => \Foundry\Enum\PaymentStatus::REFUNDED]);
    $payment->refresh();
    $this->assertTrue($payment->isRefunded());
});

it('order refund creates refund record', function () {
    $payment = $this->$createPayment('stripe');
    $refund = $this->order->refundToWallet('Test refund');
    $this->assertDatabaseHas('refunds', ['order_id' => $this->order->id, 'amount' => 100.00, 'to_wallet' => true, 'reason' => 'Test refund']);
});

it('order refund updates order totals', function () {
    $payment = $this->$createPayment('stripe');
    $originalRefundTotal = $this->order->refund_total;
    $this->order->refundToWallet('Test refund');
    $this->order->refresh();
    $this->assertEquals($originalRefundTotal + 100.00, $this->order->refund_total);
});

it('order multiple refunds throws exception', function () {
    $payment = $this->$createPayment('stripe');
    $this->order->refundToWallet('First refund');
    $this->order->refresh();
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Refund amount must be greater than zero');
    $this->order->refundToWallet('Second refund');
});

$createPayment = function (string $provider = \Foundry\Services\PaymentProvider::STRIPE, float $amount = 100.00): \Foundry\Models\Payment {
    return $this->order->payments()->create([
        'amount' => $amount, 'status' => \Foundry\Enum\PaymentStatus::COMPLETED, 'provider' => $provider, 'transaction_id' => 'txn_'.uniqid(), 'processed_at' => now(),
    ]);
};
