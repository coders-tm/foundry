<?php

namespace Foundry\Tests\Feature\Payment;

use Foundry\Exceptions\RefundException;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\PaymentMethod;
use Foundry\Models\User;
use Foundry\Payment\Processor;
use Foundry\Payment\RefundResult;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the RefundResult class and payment processor refund capabilities.
 */
class RefundProcessorTest extends TestCase
{
    protected User $user;

    protected Order $order;

    protected PaymentMethod $stripePaymentMethod;

    protected PaymentMethod $paypalPaymentMethod;

    protected PaymentMethod $walletPaymentMethod;

    protected PaymentMethod $manualPaymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->stripePaymentMethod = PaymentMethod::firstOrCreate(
            ['provider' => PaymentMethod::STRIPE],
            ['name' => 'Credit Card', 'active' => true]
        );

        $this->paypalPaymentMethod = PaymentMethod::firstOrCreate(
            ['provider' => PaymentMethod::PAYPAL],
            ['name' => 'PayPal', 'active' => true]
        );

        $this->walletPaymentMethod = PaymentMethod::firstOrCreate(
            ['provider' => PaymentMethod::WALLET],
            ['name' => 'Wallet', 'active' => true]
        );

        $this->manualPaymentMethod = PaymentMethod::firstOrCreate(
            ['provider' => PaymentMethod::MANUAL],
            ['name' => 'Manual', 'active' => true]
        );

        $this->order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'grand_total' => 100.00,
            'paid_total' => 100.00,
            'payment_status' => 'paid',
        ]);
    }

    // =========================================
    // RefundResult Class Tests
    // =========================================

    #[Test]
    public function refund_result_can_create_success()
    {
        $result = RefundResult::success(
            refundId: 'refund_123',
            amount: 50.00,
            status: 'succeeded',
            metadata: ['gateway' => 'stripe']
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('refund_123', $result->getRefundId());
        $this->assertEquals(50.00, $result->getAmount());
        $this->assertEquals('succeeded', $result->getStatus());
        $this->assertEquals(['gateway' => 'stripe'], $result->getMetadata());
    }

    #[Test]
    public function refund_result_to_array()
    {
        $result = RefundResult::success(
            refundId: 'refund_456',
            amount: 75.00,
            status: 'completed'
        );

        $array = $result->toArray();

        $this->assertTrue($array['success']);
        $this->assertEquals('refund_456', $array['refund_id']);
        $this->assertEquals(75.00, $array['amount']);
        $this->assertEquals('completed', $array['status']);
    }

    #[Test]
    public function refund_result_failed_throws_exception()
    {
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Refund failed: insufficient balance');

        RefundResult::failed('Refund failed: insufficient balance');
    }

    #[Test]
    public function refund_result_not_supported_throws_exception()
    {
        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Refund not supported for this payment method');

        RefundResult::notSupported();
    }

    #[Test]
    public function refund_exception_identifies_not_supported_type()
    {
        try {
            RefundResult::notSupported('Custom reason');
        } catch (RefundException $e) {
            $this->assertTrue($e->isNotSupported());
            $this->assertEquals('Custom reason', $e->getMessage());

            return;
        }

        $this->fail('Expected RefundException was not thrown');
    }

    // =========================================
    // Processor Refund Support Tests
    // =========================================

    #[Test]
    public function stripe_processor_supports_refund()
    {
        $processor = Processor::make('stripe');

        $this->assertTrue($processor->supportsRefund());
    }

    #[Test]
    public function paypal_processor_supports_refund()
    {
        $processor = Processor::make('paypal');

        $this->assertTrue($processor->supportsRefund());
    }

    #[Test]
    public function razorpay_processor_supports_refund()
    {
        $processor = Processor::make('razorpay');

        $this->assertTrue($processor->supportsRefund());
    }

    #[Test]
    public function flutterwave_processor_supports_refund()
    {
        $processor = Processor::make('flutterwave');

        $this->assertTrue($processor->supportsRefund());
    }

    #[Test]
    public function wallet_processor_does_not_support_refund()
    {
        $processor = Processor::make('wallet');

        $this->assertFalse($processor->supportsRefund());
    }

    #[Test]
    public function manual_processor_does_not_support_refund()
    {
        $processor = Processor::make('manual');

        $this->assertFalse($processor->supportsRefund());
    }

    // =========================================
    // Processor Refund Method Tests
    // =========================================

    #[Test]
    public function wallet_processor_throws_on_refund()
    {
        $processor = Processor::make('wallet');
        $processor->setPaymentMethod($this->walletPaymentMethod);

        $payment = $this->createPayment($this->walletPaymentMethod);

        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('Wallet payments cannot be refunded');

        $processor->refund($payment, 50.00);
    }

    #[Test]
    public function manual_processor_throws_on_refund()
    {
        $processor = Processor::make('manual');
        $processor->setPaymentMethod($this->manualPaymentMethod);

        $payment = $this->createPayment($this->manualPaymentMethod);

        $this->expectException(RefundException::class);
        $this->expectExceptionMessage('not supported');

        $processor->refund($payment, 50.00);
    }

    // =========================================
    // Payment Model Refund Tests
    // =========================================

    #[Test]
    public function payment_calculates_refundable_amount()
    {
        $payment = $this->createPayment($this->stripePaymentMethod, 100.00);

        $this->assertEquals(100.00, $payment->refundable_amount);

        // After full refund
        $payment->processRefund();
        $payment->refresh();

        $this->assertEquals(0, $payment->refundable_amount);
    }

    #[Test]
    public function payment_process_refund_updates_status()
    {
        $payment = $this->createPayment($this->stripePaymentMethod, 100.00);

        // Full refund (even if partial amount requested, it forces full)
        $payment->processRefund(40.00, 'Refund request');
        $payment->refresh();

        $this->assertEquals(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertEquals(100.00, $payment->refund_amount);
        $this->assertTrue($payment->isRefunded());
    }

    #[Test]
    public function payment_is_refunded_check()
    {
        $payment = $this->createPayment($this->stripePaymentMethod, 100.00);

        $this->assertFalse($payment->isRefunded());

        $payment->update(['status' => Payment::STATUS_REFUNDED]);
        $payment->refresh();
        $this->assertTrue($payment->isRefunded());
    }

    // =========================================
    // Order Refund Integration Tests
    // =========================================

    #[Test]
    public function order_refund_creates_refund_record()
    {
        $payment = $this->createPayment($this->stripePaymentMethod);

        $refund = $this->order->refundToWallet('Test refund');

        $this->assertDatabaseHas('refunds', [
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'to_wallet' => true,
            'reason' => 'Test refund',
        ]);
    }

    #[Test]
    public function order_refund_updates_order_totals()
    {
        $payment = $this->createPayment($this->stripePaymentMethod);
        $originalRefundTotal = $this->order->refund_total;

        $this->order->refundToWallet('Test refund');
        $this->order->refresh();

        $this->assertEquals($originalRefundTotal + 100.00, $this->order->refund_total);
    }

    #[Test]
    public function order_multiple_refunds_throws_exception()
    {
        $payment = $this->createPayment($this->stripePaymentMethod);

        $this->order->refundToWallet('First refund');
        $this->order->refresh();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Refund amount must be greater than zero');

        $this->order->refundToWallet('Second refund');
    }

    protected function createPayment(PaymentMethod $method, float $amount = 100.00): Payment
    {
        return $this->order->payments()->create([
            'amount' => $amount,
            'status' => Payment::STATUS_COMPLETED,
            'payment_method_id' => $method->id,
            'transaction_id' => 'txn_'.uniqid(),
            'processed_at' => now(),
        ]);
    }
}
