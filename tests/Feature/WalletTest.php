<?php

use Foundry\Exceptions\PaymentException;
use Foundry\Models\Order;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Models\WalletBalance;
use Foundry\Models\WalletTransaction;
use Foundry\Payment\Payable;
use Foundry\Payment\Processor;
use Foundry\Tests\TestCase;
use Illuminate\Http\Request;

uses(TestCase::class);

beforeEach(function () {
    $this->user = \Foundry\Models\User::factory()->create();
});

it('can get or create wallet', function () {
    $wallet = $this->user->getOrCreateWallet();

    $this->assertInstanceOf(WalletBalance::class, $wallet);
    $this->assertEquals(0.00, $wallet->balance);
    $this->assertEquals($this->user->id, $wallet->user_id);
});

it('can credit wallet', function () {
    $transaction = $this->user->creditWallet(
        amount: 100.00,
        source: 'test',
        description: 'Test credit'
    );

    $this->assertInstanceOf(WalletTransaction::class, $transaction);
    $this->assertEquals('credit', $transaction->type);
    $this->assertEquals(100.00, $transaction->amount);
    $this->assertEquals(100.00, $this->user->getWalletBalance());
});

it('can debit wallet', function () {
    $this->user->creditWallet(100.00, 'test', 'Initial balance');

    $transaction = $this->user->debitWallet(
        amount: 50.00,
        source: 'test',
        description: 'Test debit'
    );

    $this->assertEquals('debit', $transaction->type);
    $this->assertEquals(50.00, $transaction->amount);
    $this->assertEquals(50.00, $this->user->getWalletBalance());
});

it('cannot debit more than wallet balance', function () {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Insufficient wallet balance');

    $this->user->creditWallet(50.00, 'test', 'Initial balance');
    $this->user->debitWallet(100.00, 'test', 'Over limit');
});

it('tracks balance changes in wallet transactions', function () {
    $this->user->creditWallet(100.00, 'test', 'First credit');
    $transaction = $this->user->creditWallet(50.00, 'test', 'Second credit');

    $this->assertEquals(100.00, $transaction->balance_before);
    $this->assertEquals(150.00, $transaction->balance_after);
});

it('can refund order to wallet', function () {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'grand_total' => 100.00,
        'payment_status' => 'paid',
    ]);

    $order->payments()->create([
        'amount' => 100.00,
        'currency' => 'usd',
        'status' => 'completed',
        'provider' => 'wallet',
    ]);

    /** @var Order $order */
    $order = $order->fresh();

    $refund = $order->refundToWallet('Customer requested refund');

    $this->assertEquals(100.00, $refund->amount);
    $this->assertTrue($refund->to_wallet);
    $this->assertEquals(100.00, $this->user->fresh()->getWalletBalance());
});

it('charges from wallet on renewal if balance available', function () {
    config(['foundry.wallet.auto_charge_on_renewal' => false]);

    $plan = Plan::factory()->create([
        'price' => 50.00,
        'interval' => 'month',
        'interval_count' => 1,
    ]);

    $subscription = $this->user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    $subscription->paymentConfirmation();

    config(['foundry.wallet.auto_charge_on_renewal' => true]);
    $this->user->creditWallet(100.00, 'test', 'Initial balance');

    $subscription->update([
        'expires_at' => now()->subDay(),
    ]);

    $subscription->renew();

    $walletBalance = $this->user->fresh()->getWalletBalance();

    $transactions = $this->user->walletTransactions()->orderBy('id', 'desc')->get();

    $this->assertEquals(
        45.00,
        $walletBalance,
        'Wallet balance should be 45 (100 - 55 with tax). Transactions: '.
            $transactions->pluck('description', 'amount')->toJson()
    );

    $this->assertTrue($subscription->fresh()->active());
    $this->assertNull($subscription->fresh()->ends_at);
});

it('enters grace period if wallet balance insufficient on renewal', function () {
    $plan = Plan::factory()->withGracePeriod(7)->create([
        'price' => 100.00,
        'interval' => 'month',
        'interval_count' => 1,
    ]);

    $this->user->creditWallet(50.00, 'test', 'Initial balance');

    $subscription = $this->user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    $subscription->paymentConfirmation();

    $subscription->update([
        'expires_at' => now()->subDay(),
    ]);

    $subscription->renew();

    $this->assertEquals(50.00, $this->user->fresh()->getWalletBalance());

    $this->assertNotNull($subscription->fresh()->ends_at);
});

it('can setup payment intent via wallet processor', function () {
    $this->user->creditWallet(
        amount: 100.00,
        source: 'test',
        description: 'Initial balance'
    );

    $processor = Processor::make('wallet');

    $payable = Payable::make([
        'grand_total' => 50.00,
        'tax_total' => 0.00,
        'shipping_total' => 0.00,
    ]);

    $request = Request::create('/api/shop/wallet/setup-payment-intent');
    $request->setUserResolver(fn () => $this->user->fresh());

    $result = $processor->setupPaymentIntent($request, $payable);

    $this->assertEquals('Wallet payment ready', $result['message']);
    $this->assertEquals(50.00, $result['amount']);
    $this->assertEquals(100.00, $result['wallet_balance']);
    $this->assertTrue($result['has_sufficient_balance']);
});

it('can confirm payment via wallet processor', function () {
    $this->user->creditWallet(100.00, 'test', 'Initial balance');

    $processor = Processor::make('wallet');

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
});

it('fails wallet payment processor with insufficient balance', function () {
    $this->user->creditWallet(30.00, 'test', 'Initial balance');

    $processor = Processor::make('wallet');

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
});

it('can view wallet balance', function () {
    $this->user->creditWallet(150.00, 'test', 'Test balance');

    $response = $this->actingAs($this->user, 'user')
        ->getJson('/user/wallet/balance');

    $response->assertStatus(200)
        ->assertJson([
            'balance' => 150,
            'currency' => 'USD',
        ]);
});

it('can view wallet transactions', function () {
    $this->user->creditWallet(100.00, 'test', 'First transaction');
    $this->user->creditWallet(50.00, 'test', 'Second transaction');
    $this->user->debitWallet(25.00, 'test', 'Third transaction');

    $response = $this->actingAs($this->user, 'user')
        ->getJson('/user/wallet/transactions');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('links wallet transactions to transactionable', function () {
    $order = Order::factory()->create([
        'customer_id' => $this->user->id,
        'grand_total' => 100.00,
    ]);

    $order->payments()->create([
        'amount' => 100.00,
        'status' => 'completed',
        'provider' => 'wallet',
    ]);

    $order = $order->fresh();

    $refund = $order->refundToWallet(50.00, 'Test refund');
    $transaction = $refund->walletTransaction;

    $this->assertEquals(get_class($order), $transaction->transactionable_type);
    $this->assertEquals($order->id, $transaction->transactionable_id);
});

it('can disable wallet auto charge via config', function () {
    config(['foundry.wallet.auto_charge_on_renewal' => false]);

    $plan = Plan::factory()->withGracePeriod(7)->create(['price' => 50.00]);

    $this->user->creditWallet(100.00, 'test', 'Initial balance');

    $subscription = $this->user->newSubscription('default', $plan->id)
        ->saveAndInvoice([], true);

    $subscription->paymentConfirmation();

    $subscription->update(['expires_at' => now()->subDay()]);
    $subscription->renew();

    $this->assertEquals(100.00, $this->user->fresh()->getWalletBalance());

    $this->assertNotNull($subscription->fresh()->ends_at);
});
