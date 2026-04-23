<?php

namespace Foundry\Tests\Feature;

use Foundry\Exceptions\PaymentException;
use Foundry\Models\Order;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Models\WalletBalance;
use Foundry\Models\WalletTransaction;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Foundry\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class WalletTest extends TestCase
{
    protected User $user;

    protected PaymentMethod $walletPaymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create wallet payment method
        $this->walletPaymentMethod = PaymentMethod::firstOrCreate(
            ['provider' => PaymentMethod::WALLET],
            [
                'name' => 'Wallet Balance',
                'active' => true,
                'logo' => 'fas fa-wallet',
                'description' => 'Pay using your wallet balance',
            ]
        );
    }

    #[Test]
    public function user_can_get_or_create_wallet()
    {
        $wallet = $this->user->getOrCreateWallet();

        $this->assertInstanceOf(WalletBalance::class, $wallet);
        $this->assertEquals(0.00, $wallet->balance);
        $this->assertEquals($this->user->id, $wallet->user_id);
    }

    #[Test]
    public function user_can_credit_wallet()
    {
        $transaction = $this->user->creditWallet(
            amount: 100.00,
            source: 'test',
            description: 'Test credit'
        );

        $this->assertInstanceOf(WalletTransaction::class, $transaction);
        $this->assertEquals('credit', $transaction->type);
        $this->assertEquals(100.00, $transaction->amount);
        $this->assertEquals(100.00, $this->user->getWalletBalance());
    }

    #[Test]
    public function user_can_debit_wallet()
    {
        $this->user->creditWallet(100.00, 'test', 'Initial balance');

        $transaction = $this->user->debitWallet(
            amount: 50.00,
            source: 'test',
            description: 'Test debit'
        );

        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals(50.00, $transaction->amount);
        $this->assertEquals(50.00, $this->user->getWalletBalance());
    }

    #[Test]
    public function cannot_debit_more_than_wallet_balance()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $this->user->creditWallet(50.00, 'test', 'Initial balance');
        $this->user->debitWallet(100.00, 'test', 'Over limit');
    }

    #[Test]
    public function wallet_transactions_track_balance_changes()
    {
        $this->user->creditWallet(100.00, 'test', 'First credit');
        $transaction = $this->user->creditWallet(50.00, 'test', 'Second credit');

        $this->assertEquals(100.00, $transaction->balance_before);
        $this->assertEquals(150.00, $transaction->balance_after);
    }

    #[Test]
    public function order_can_be_refunded_to_wallet()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'grand_total' => 100.00,
            'payment_status' => 'paid',
        ]);

        // Create a payment for the order
        $order->payments()->create([
            'amount' => 100.00,
            'currency' => 'usd',
            'status' => 'completed', // Payment::STATUS_COMPLETED
            'payment_method_id' => $this->walletPaymentMethod->id,
        ]);

        // Reload order to get updated paid_total (now a real column, auto-updated by Payment observer)
        /** @var Order $order */
        $order = $order->fresh();

        $refund = $order->refundToWallet('Customer requested refund');

        $this->assertEquals(100.00, $refund->amount);
        $this->assertTrue($refund->to_wallet);
        $this->assertEquals(100.00, $this->user->fresh()->getWalletBalance());
    }

    #[Test]
    public function subscription_charges_from_wallet_on_renewal_if_balance_available()
    {
        // Disable wallet auto-charge for initial subscription creation
        config(['foundry.wallet.auto_charge_on_renewal' => false]);

        // Create a plan
        $plan = Plan::factory()->create([
            'price' => 50.00,
            'interval' => 'month',
            'interval_count' => 1,
        ]);

        // Create subscription
        $subscription = $this->user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $subscription->paymentConfirmation();

        // Now enable wallet auto-charge and add balance
        config(['foundry.wallet.auto_charge_on_renewal' => true]);
        $this->user->creditWallet(100.00, 'test', 'Initial balance');

        // Manually trigger renewal (simulate time passing)
        $subscription->update([
            'expires_at' => now()->subDay(),
        ]);

        $subscription->renew();

        // Check that wallet was charged
        $walletBalance = $this->user->fresh()->getWalletBalance();

        // Get all wallet transactions to see what was charged
        $transactions = $this->user->walletTransactions()->orderBy('id', 'desc')->get();

        // Should be 100 - (plan price 50 + tax 5) = 45
        $this->assertEquals(
            45.00,
            $walletBalance,
            'Wallet balance should be 45 (100 - 55 with tax). Transactions: '.
                $transactions->pluck('description', 'amount')->toJson()
        );

        // Check that subscription is active (not in grace period)
        $this->assertTrue($subscription->fresh()->active());
        $this->assertNull($subscription->fresh()->ends_at);
    }

    #[Test]
    public function subscription_enters_grace_period_if_wallet_balance_insufficient()
    {
        // Create a plan with grace period enabled
        $plan = Plan::factory()->withGracePeriod(7)->create([
            'price' => 100.00,
            'interval' => 'month',
            'interval_count' => 1,
        ]);

        // Add insufficient wallet balance
        $this->user->creditWallet(50.00, 'test', 'Initial balance');

        // Create subscription
        $subscription = $this->user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $subscription->paymentConfirmation();

        // Manually trigger renewal
        $subscription->update([
            'expires_at' => now()->subDay(),
        ]);

        $subscription->renew();

        // Check that wallet was NOT charged (insufficient balance)
        $this->assertEquals(50.00, $this->user->fresh()->getWalletBalance());

        // Check that subscription is in grace period (ends_at should be set since plan has grace period)
        $this->assertNotNull($subscription->fresh()->ends_at);
    }

    #[Test]
    public function wallet_payment_processor_can_setup_payment_intent()
    {
        // Add wallet balance first
        $this->user->creditWallet(
            amount: 100.00,
            source: 'test',
            description: 'Initial balance'
        );

        $processor = Processor::make('wallet');
        $processor->setPaymentMethod($this->walletPaymentMethod);

        $payable = Payable::make([
            'grand_total' => 50.00,
            'tax_total' => 0.00,
            'shipping_total' => 0.00,
        ]);

        $request = Request::create('/api/shop/wallet/setup-payment-intent');
        // Return fresh user with wallet balance
        $request->setUserResolver(fn () => $this->user->fresh());

        $result = $processor->setupPaymentIntent($request, $payable);

        $this->assertEquals('Wallet payment ready', $result['message']);
        $this->assertEquals(50.00, $result['amount']);
        $this->assertEquals(100.00, $result['wallet_balance']);
        $this->assertTrue($result['has_sufficient_balance']);
    }

    #[Test]
    public function wallet_payment_processor_can_confirm_payment()
    {
        $this->user->creditWallet(100.00, 'test', 'Initial balance');

        $processor = Processor::make('wallet');
        $processor->setPaymentMethod($this->walletPaymentMethod);

        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'grand_total' => 50.00,
        ]);

        $payable = Payable::fromOrder($order);

        $request = Request::create('/api/shop/wallet/confirm-payment');
        $request->setUserResolver(fn () => $this->user);

        $result = $processor->confirmPayment($request, $payable);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('succeeded', $result->getStatus());
        $this->assertEquals(50.00, $this->user->fresh()->getWalletBalance());
    }

    #[Test]
    public function wallet_payment_processor_fails_with_insufficient_balance()
    {
        $this->user->creditWallet(30.00, 'test', 'Initial balance');

        $processor = Processor::make('wallet');
        $processor->setPaymentMethod($this->walletPaymentMethod);

        $payable = Payable::make([
            'grand_total' => 50.00,
            'currency' => 'usd',
            'tax_total' => 0.00,
            'shipping_total' => 0.00,
        ]);

        $request = Request::create('/api/shop/wallet/confirm-payment');
        $request->setUserResolver(fn () => $this->user);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $processor->confirmPayment($request, $payable);
    }

    #[Test]
    public function user_can_view_wallet_balance()
    {
        $this->user->creditWallet(150.00, 'test', 'Test balance');

        $response = $this->actingAs($this->user, 'user')
            ->getJson('/user/wallet/balance');

        $response->assertStatus(200)
            ->assertJson([
                'balance' => 150,
                'currency' => 'USD',
            ]);
    }

    #[Test]
    public function user_can_view_wallet_transactions()
    {
        $this->user->creditWallet(100.00, 'test', 'First transaction');
        $this->user->creditWallet(50.00, 'test', 'Second transaction');
        $this->user->debitWallet(25.00, 'test', 'Third transaction');

        $response = $this->actingAs($this->user, 'user')
            ->getJson('/user/wallet/transactions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function wallet_transactions_are_linked_to_transactionable()
    {
        $order = Order::factory()->create([
            'customer_id' => $this->user->id,
            'grand_total' => 100.00,
        ]);

        // Create a payment for the order
        $order->payments()->create([
            'amount' => 100.00,
            'status' => 'completed', // Payment::STATUS_COMPLETED
            'payment_method_id' => $this->walletPaymentMethod->id,
        ]);

        // Reload order to get updated paid_total (auto-updated by Payment observer)
        $order = $order->fresh();

        $refund = $order->refundToWallet(50.00, 'Test refund');
        $transaction = $refund->walletTransaction;

        $this->assertEquals(get_class($order), $transaction->transactionable_type);
        $this->assertEquals($order->id, $transaction->transactionable_id);
    }

    #[Test]
    public function wallet_auto_charge_can_be_disabled_via_config()
    {
        config(['foundry.wallet.auto_charge_on_renewal' => false]);

        // Create plan with grace period enabled
        $plan = Plan::factory()->withGracePeriod(7)->create(['price' => 50.00]);

        // Add sufficient wallet balance
        $this->user->creditWallet(100.00, 'test', 'Initial balance');

        $subscription = $this->user->newSubscription('default', $plan->id)
            ->saveAndInvoice([], true);

        $subscription->paymentConfirmation();

        // Trigger renewal
        $subscription->update(['expires_at' => now()->subDay()]);
        $subscription->renew();

        // Wallet should NOT be charged (auto_charge disabled)
        $this->assertEquals(100.00, $this->user->fresh()->getWalletBalance());

        // Subscription should be in grace period (ends_at set since plan has grace period)
        $this->assertNotNull($subscription->fresh()->ends_at);
    }
}
