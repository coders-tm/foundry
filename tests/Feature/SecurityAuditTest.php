<?php

use Foundry\Enum\OrderStatus;
use Foundry\Enum\PaymentStatus;
use Foundry\Models\Admin;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\Permission;
use Foundry\Models\User;
use Foundry\Rules\ReCaptchaRule;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

it('has any permission returns false when access is zero', function () {
    $admin = Admin::factory()->create(['is_super_admin' => false]);

    $permission = Permission::firstOrCreate(
        ['scope' => 'test.denied'],
        ['module_key' => 'test', 'name' => 'Test Denied', 'label' => 'Test Denied', 'action' => 'read']
    );

    $admin->permissions()->sync([
        $permission->scope => ['access' => 0],
    ]);

    $admin->unsetRelation('permissions');

    expect($admin->hasAnyPermission('test.denied'))->toBeFalse();
});

it('has any permission returns true when access is one', function () {
    $admin = Admin::factory()->create(['is_super_admin' => false]);

    $permission = Permission::firstOrCreate(
        ['scope' => 'test.allowed'],
        ['module_key' => 'test', 'name' => 'Test Allowed', 'label' => 'Test Allowed', 'action' => 'read']
    );

    $admin->permissions()->sync([
        $permission->scope => ['access' => 1],
    ]);

    $admin->unsetRelation('permissions');

    expect($admin->hasAnyPermission('test.allowed'))->toBeTrue();
});

it('wallet debit throws on insufficient balance', function () {
    $user = User::factory()->create();
    $user->creditWallet(50.00, 'test-setup');

    expect(fn () => $user->debitWallet(100.00, 'test-overdraft'))->toThrow(Exception::class, 'Insufficient');
});

it('wallet debit reduces balance exactly', function () {
    $user = User::factory()->create();
    $user->creditWallet(100.00, 'test-setup');
    $user->debitWallet(40.00, 'test-debit');

    expect($user->fresh()->getWalletBalance())->toEqual(60.00);
});

it('mark as paid rejects under payment', function () {
    $order = Order::factory()->create(['grand_total' => 100.00]);

    expect(fn () => $order->markAsPaid(
        payment: 1,
        transaction: ['id' => 'txn_test', 'amount' => 0.01]
    ))->toThrow(InvalidArgumentException::class, 'Payment amount mismatch');
});

it('mark as paid accepts exact amount', function () {
    $order = Order::factory()->create([
        'grand_total' => 50.00,
        'status' => OrderStatus::PENDING,
    ]);

    $order->markAsPaid(
        payment: 1,
        transaction: ['id' => 'txn_exact', 'amount' => 50.00]
    );

    expect($order->fresh()->payment_status)->toEqual(PaymentStatus::PAID);
});

it('create for order deduplicates on transaction id', function () {
    $order = Order::factory()->create(['grand_total' => 10.00]);

    $attrs = [
        'transaction_id' => 'txn_idempotent_001',
        'amount' => 10.00,
        'status' => PaymentStatus::COMPLETED,
    ];

    Payment::createForOrder($order, $attrs);
    Payment::createForOrder($order, $attrs);

    $count = Payment::where('transaction_id', 'txn_idempotent_001')->count();
    expect($count)->toEqual(1);
});

it('create for order creates separate records without transaction id', function () {
    $order = Order::factory()->create(['grand_total' => 10.00]);

    $attrs = ['amount' => 10.00, 'status' => PaymentStatus::COMPLETED];

    Payment::createForOrder($order, $attrs);
    Payment::createForOrder($order, $attrs);

    $count = Payment::where('paymentable_id', $order->id)->count();
    expect($count)->toEqual(2);
});

it('recaptcha fails when score is below threshold', function () {
    Http::fake([
        ReCaptchaRule::URL => Http::response([
            'success' => true,
            'score' => 0.3,
        ]),
    ]);

    $rule = new ReCaptchaRule;
    expect($rule->passes('token', 'fake-token'))->toBeFalse();
});

it('recaptcha fails when success is false even with high score', function () {
    Http::fake([
        ReCaptchaRule::URL => Http::response([
            'success' => false,
            'score' => 0.9,
        ]),
    ]);

    $rule = new ReCaptchaRule;
    expect($rule->passes('token', 'fake-token'))->toBeFalse();
});

it('recaptcha passes when success true and score above threshold', function () {
    Http::fake([
        ReCaptchaRule::URL => Http::response([
            'success' => true,
            'score' => 0.7,
        ]),
    ]);

    $rule = new ReCaptchaRule;
    expect($rule->passes('token', 'fake-token'))->toBeTrue();
});

it('user fillable does not include privileged fields', function () {
    $user = new User;
    $fillable = $user->getFillable();

    foreach (['is_active', 'rag', 'status', 'is_free_forever'] as $field) {
        expect($fillable)->not->toContain($field);
    }
});

it('admin fillable does not include is super admin', function () {
    $admin = new Admin;
    expect($admin->getFillable())->not->toContain('is_super_admin');
});

it('user scope sort by falls back to created at for unknown column', function () {
    User::factory()->count(3)->create();

    $query = User::query()->sortBy('id; DROP TABLE users--');

    $sql = $query->toSql();

    expect($sql)->toContain('created_at');
    expect($sql)->not->toContain('DROP');
});

it('user scope sort by rejects invalid direction', function () {
    User::factory()->count(2)->create();

    $query = User::query()->sortBy('created_at', 'DESC; DROP TABLE users--');
    $sql = $query->toSql();

    expect($sql)->not->toContain('DROP');
});

it('user json does not expose guard', function () {
    $user = User::factory()->create();
    $json = $user->toArray();

    expect($json)->not->toHaveKey('guard');
});

it('admin json does not expose guard', function () {
    $admin = Admin::factory()->create();
    $json = $admin->toArray();

    expect($json)->not->toHaveKey('guard');
});
