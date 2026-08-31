<?php

namespace Tests\Feature\Mandate;

use Foundry\Foundry;
use Foundry\Mandate\BillerManager;
use Foundry\Mandate\Exceptions\PaymentIncomplete;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Payment\Payable;
use Foundry\Payment\PaymentResult;
use Foundry\Services\PaymentProvider;
use Foundry\Tests\TestCase;
use Paddle\SDK\Client as PaddleSdkClient;
use Paddle\SDK\Entities\Shared\CurrencyCode;
use Paddle\SDK\Entities\Shared\TransactionStatus;
use Paddle\SDK\Entities\Transaction;
use Paddle\SDK\Resources\Transactions\TransactionsClient;
use PHPUnit\Framework\Attributes\Test;

class PaddleBillableTest extends TestCase
{
    protected function tearDown(): void
    {
        Foundry::setPaddleClient(null);
        parent::tearDown();
    }

    /**
     * Test setup auto-renewal for Paddle provider returns SDK transaction setup data.
     */
    #[Test]
    public function test_setup_paddle_auto_renewal()
    {
        $subscription = Subscription::factory()->create(['provider' => PaymentProvider::PADDLE]);

        $mockTransaction = $this->createMock(Transaction::class);
        $mockTransaction->id = 'txn_paddle_setup_123';

        $mockTransactionsClient = $this->createMock(TransactionsClient::class);
        $mockTransactionsClient->expects($this->once())
            ->method('create')
            ->willReturn($mockTransaction);

        $mockPaddleClient = $this->createMock(PaddleSdkClient::class);
        $refProp = new \ReflectionProperty(PaddleSdkClient::class, 'transactions');
        $refProp->setValue($mockPaddleClient, $mockTransactionsClient);

        Foundry::setPaddleClient($mockPaddleClient);

        $manager = new BillerManager($subscription->user);
        $manager->setProvider(PaymentProvider::PADDLE);
        $result = $manager->setup();

        $this->assertIsArray($result);
        $this->assertEquals('sdk', $result['action']);
        $this->assertEquals(PaymentProvider::PADDLE, $result['provider']);
        $this->assertEquals('txn_paddle_setup_123', $result['transaction_id']);

        $this->assertDatabaseHas('payment_provider_customers', [
            'user_id' => $subscription->user_id,
            'provider' => PaymentProvider::PADDLE,
        ]);
    }

    /**
     * Test confirm Paddle payment method mandate.
     */
    #[Test]
    public function test_confirm_paddle_payment_method()
    {
        $subscription = Subscription::factory()->create(['provider' => PaymentProvider::PADDLE]);

        $manager = new BillerManager($subscription->user);
        $manager->setProvider(PaymentProvider::PADDLE);
        $pm = $manager->confirm(PaymentProvider::PADDLE, [
            'payment_method_id' => 'pay_mtd_paddle_test_123',
            'transaction_id' => 'txn_paddle_setup_123',
        ]);

        $this->assertDatabaseHas('users_payment_methods', [
            'user_id' => $subscription->user_id,
            'provider' => PaymentProvider::PADDLE,
            'provider_id' => 'pay_mtd_paddle_test_123',
        ]);
    }

    /**
     * Test auto-renewal removal for Paddle.
     */
    #[Test]
    public function test_remove_paddle_auto_renewal()
    {
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::PADDLE,
            'auto_renewal_enabled' => true,
        ]);

        $manager = new BillerManager($subscription->user, 'pm_paddle_123');
        $manager->setProvider(PaymentProvider::PADDLE);
        $manager->setup();

        $manager = new BillerManager($subscription->user);
        $manager->setProvider(PaymentProvider::PADDLE);
        $result = $manager->removePaymentMethod();

        $this->assertTrue($result);

        $this->assertDatabaseMissing('users_payment_methods', [
            'user_id' => $subscription->user_id,
            'provider' => PaymentProvider::PADDLE,
        ]);
    }

    /**
     * Test charging a Payable entity via Paddle off-session.
     */
    #[Test]
    public function test_charge_paddle_auto_renewal()
    {
        $plan = Plan::factory()->create(['price' => 20.00]);
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::PADDLE,
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $subscription->user_id,
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'grand_total' => 20.00,
        ]);

        $manager = new BillerManager($subscription->user, 'pm_paddle_123');
        $manager->setProvider(PaymentProvider::PADDLE);
        $manager->setup();

        // Mock Paddle SDK client & transactions client
        $mockTransaction = $this->createMock(Transaction::class);
        $mockTransaction->id = 'txn_paddle_999';
        $mockTransaction->status = TransactionStatus::Completed();
        $mockTransaction->currencyCode = CurrencyCode::from('USD');

        $mockTransactionsClient = $this->createMock(TransactionsClient::class);
        $mockTransactionsClient->expects($this->once())
            ->method('create')
            ->willReturn($mockTransaction);

        $mockPaddleClient = $this->createMock(PaddleSdkClient::class);
        $refProp = new \ReflectionProperty(PaddleSdkClient::class, 'transactions');
        $refProp->setValue($mockPaddleClient, $mockTransactionsClient);

        Foundry::setPaddleClient($mockPaddleClient);

        $payable = Payable::fromOrder($order);
        $manager = new BillerManager($subscription->user);
        $result = $manager->charge($payable);

        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('txn_paddle_999', $result->getTransactionId());
        $this->assertEquals('succeeded', $result->getStatus());
    }

    /**
     * Test Paddle charge requiring action.
     */
    #[Test]
    public function test_charge_paddle_requires_action()
    {
        $plan = Plan::factory()->create(['price' => 20.00]);
        $subscription = Subscription::factory()->create([
            'provider' => PaymentProvider::PADDLE,
            'plan_id' => $plan->id,
            'auto_renewal_enabled' => true,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $subscription->user_id,
            'orderable_id' => $subscription->id,
            'orderable_type' => Subscription::class,
            'grand_total' => 20.00,
        ]);

        $manager = new BillerManager($subscription->user, 'pm_paddle_3ds');
        $manager->setProvider(PaymentProvider::PADDLE);
        $manager->setup();

        $mockTransaction = $this->createMock(Transaction::class);
        $mockTransaction->id = 'txn_paddle_3ds';
        $mockTransaction->status = TransactionStatus::from('requires_action');

        $mockTransactionsClient = $this->createMock(TransactionsClient::class);
        $mockTransactionsClient->expects($this->once())
            ->method('create')
            ->willReturn($mockTransaction);

        $mockPaddleClient = $this->createMock(PaddleSdkClient::class);
        $refProp = new \ReflectionProperty(PaddleSdkClient::class, 'transactions');
        $refProp->setValue($mockPaddleClient, $mockTransactionsClient);

        Foundry::setPaddleClient($mockPaddleClient);

        try {
            $payable = Payable::fromOrder($order);
            $manager = new BillerManager($subscription->user);
            $manager->charge($payable);
            $this->fail('Expected PaymentIncomplete exception was not thrown.');
        } catch (PaymentIncomplete $e) {
            $this->assertInstanceOf(PaymentIncomplete::class, $e);
            $this->assertEquals('requires_action', $e->payment()->status());
        }
    }
}
