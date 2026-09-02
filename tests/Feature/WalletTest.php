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
    $this->user = User::factory()->create();
});

it('can get or create wallet', function () {
    $wallet = $this->user->getOrCreateWallet();

    expect($wallet)->toBeInstanceOf(WalletBalance::class);
    expect($wallet->balance)->toEqual(0.00);
    expect($wallet->user_id)->toEqual($this->user->id);
});

it('can credit wallet', function () {
    $transaction = $this->user->creditWallet(
        amount: 100.00,
        source: 'test',
        description: 'Test credit'
    );

    expect($transaction)->toBeInstanceOf(WalletTransaction::class);
    expect($transaction->type)->toEqual('credit');
    expect($transaction->amount)->toEqual(100.00);
    expect($this->user->getWalletBalance())->toEqual(100.00);
});

it('can debit wallet', function () {
    $this->user->creditWallet(100.00, 'test', 'Initial balance');

    $transaction = $this->user->debitWallet(
        amount: 50.00,
        source: 'test',
        description: 'Test debit'
    );

    expect($transaction->type)->toEqual('debit');
    expect($transaction->amount)->toEqual(50.00);
    expect($this->user->getWalletBalance())->toEqual(50.00);
});

it('cannot debit more than wallet balance', function () {
    $this->user->creditWallet(50.00, 'test', 'Initial balance');

    expect(fn () => $this->user->debitWallet(100.00, 'test', 'Over limit'))->toThrow(Exception::class, 'Insufficient wallet balance');
});

it('tracks balance changes in wallet transactions', function () {
    $this->user->creditWallet(100.00, 'test', 'First credit');
    $transaction = $this->user->creditWallet(50.00, 'test', 'Second credit');

    expect($transaction->balance_before)->toEqual(100.00);
    expect($transaction->balance_after)->toEqual(150.00);
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

    expect($refund->amount)->toEqual(100.00);
    expect($refund->to_wallet)->toBeTrue();
    expect($this->user->fresh()->getWalletBalance())->toEqual(100.00);
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

    expect($walletBalance)->toEqual(45.00);

    expect($subscription->fresh()->active())->toBeTrue();
    expect($subscription->fresh()->ends_at)->toBeNull();
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

    expect($this->user->fresh()->getWalletBalance())->toEqual(50.00);

    expect($subscription->fresh()->ends_at)->not->toBeNull();
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

    expect($result['message'])->toEqual('Wallet payment ready');
    expect($result['amount'])->toEqual(50.00);
    expect($result['wallet_balance'])->toEqual(100.00);
    expect($result['has_sufficient_balance'])->toBeTrue();
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

    expect($result->isSuccess())->toBeTrue();
    expect($result->getStatus())->toEqual('succeeded');
    expect($this->user->fresh()->getWalletBalance())->toEqual(50.00);
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

    expect(fn () => $processor->confirmPayment($request, $payable))->toThrow(PaymentException::class, 'Insufficient wallet balance');
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

    expect(get_class($order))->toEqual($transaction->transactionable_type);
    expect($order->id)->toEqual($transaction->transactionable_id);
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

    expect($this->user->fresh()->getWalletBalance())->toEqual(100.00);

    expect($subscription->fresh()->ends_at)->not->toBeNull();
});
