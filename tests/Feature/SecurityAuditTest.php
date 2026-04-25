<?php

namespace Foundry\Tests\Feature;

use Foundry\Enum\OrderStatus as OrderStatusEnum;
use Foundry\Enum\PaymentStatus;
use Foundry\Models\Admin;
use Foundry\Models\Order;
use Foundry\Models\Payment;
use Foundry\Models\Permission;
use Foundry\Models\User;
use Foundry\Rules\ReCaptchaRule;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class SecurityAuditTest extends TestCase
{
    #[Test]
    public function has_any_permission_returns_false_when_access_is_zero()
    {
        $admin = Admin::factory()->create(['is_super_admin' => false]);

        $permission = Permission::firstOrCreate(
            ['scope' => 'test.denied'],
            ['module_key' => 'test', 'name' => 'Test Denied', 'label' => 'Test Denied', 'action' => 'read']
        );

        $admin->permissions()->sync([
            $permission->scope => ['access' => 0],
        ]);

        $admin->unsetRelation('permissions');

        $this->assertFalse(
            $admin->hasAnyPermission('test.denied'),
            'hasAnyPermission should return false when pivot.access = 0'
        );
    }

    #[Test]
    public function has_any_permission_returns_true_when_access_is_one()
    {
        $admin = Admin::factory()->create(['is_super_admin' => false]);

        $permission = Permission::firstOrCreate(
            ['scope' => 'test.allowed'],
            ['module_key' => 'test', 'name' => 'Test Allowed', 'label' => 'Test Allowed', 'action' => 'read']
        );

        $admin->permissions()->sync([
            $permission->scope => ['access' => 1],
        ]);

        $admin->unsetRelation('permissions');

        $this->assertTrue(
            $admin->hasAnyPermission('test.allowed'),
            'hasAnyPermission should return true when pivot.access = 1'
        );
    }

    #[Test]
    public function wallet_debit_throws_on_insufficient_balance()
    {
        $user = User::factory()->create();
        $user->creditWallet(50.00, 'test-setup');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/insufficient/i');

        $user->debitWallet(100.00, 'test-overdraft');
    }

    #[Test]
    public function wallet_debit_reduces_balance_exactly()
    {
        $user = User::factory()->create();
        $user->creditWallet(100.00, 'test-setup');
        $user->debitWallet(40.00, 'test-debit');

        $this->assertEquals(60.00, $user->fresh()->getWalletBalance());
    }

    #[Test]
    public function mark_as_paid_rejects_under_payment()
    {
        $order = Order::factory()->create(['grand_total' => 100.00]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/payment amount mismatch/i');

        $order->markAsPaid(
            payment: 1, // any payment method id
            transaction: ['id' => 'txn_test', 'amount' => 0.01]
        );
    }

    #[Test]
    public function mark_as_paid_accepts_exact_amount()
    {
        $order = Order::factory()->create([
            'grand_total' => 50.00,
            'status' => OrderStatusEnum::PENDING_PAYMENT,
        ]);

        // Should not throw — amount equals grand_total
        $order->markAsPaid(
            payment: 1,
            transaction: ['id' => 'txn_exact', 'amount' => 50.00]
        );

        $this->assertEquals(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    #[Test]
    public function create_for_order_deduplicates_on_transaction_id()
    {
        $order = Order::factory()->create(['grand_total' => 10.00]);

        $attrs = [
            'transaction_id' => 'txn_idempotent_001',
            'amount' => 10.00,
            'status' => PaymentStatus::COMPLETED,
        ];

        Payment::createForOrder($order, $attrs);
        Payment::createForOrder($order, $attrs); // duplicate webhook replay

        $count = Payment::where('transaction_id', 'txn_idempotent_001')->count();
        $this->assertEquals(1, $count, 'Duplicate webhook replay must not create a second payment record');
    }

    #[Test]
    public function create_for_order_creates_separate_records_without_transaction_id()
    {
        $order = Order::factory()->create(['grand_total' => 10.00]);

        $attrs = ['amount' => 10.00, 'status' => PaymentStatus::COMPLETED];

        Payment::createForOrder($order, $attrs);
        Payment::createForOrder($order, $attrs);

        $count = Payment::where('paymentable_id', $order->id)->count();
        $this->assertEquals(2, $count, 'Two offline payments must create two separate records');
    }

    #[Test]
    public function recaptcha_fails_when_score_is_below_threshold()
    {
        Http::fake([
            ReCaptchaRule::URL => Http::response([
                'success' => true,
                'score' => 0.3, // below 0.5 threshold
            ]),
        ]);

        $rule = new ReCaptchaRule;
        $this->assertFalse($rule->passes('token', 'fake-token'));
    }

    #[Test]
    public function recaptcha_fails_when_success_is_false_even_with_high_score()
    {
        Http::fake([
            ReCaptchaRule::URL => Http::response([
                'success' => false,
                'score' => 0.9,
            ]),
        ]);

        $rule = new ReCaptchaRule;
        $this->assertFalse($rule->passes('token', 'fake-token'));
    }

    #[Test]
    public function recaptcha_passes_when_success_true_and_score_above_threshold()
    {
        Http::fake([
            ReCaptchaRule::URL => Http::response([
                'success' => true,
                'score' => 0.7,
            ]),
        ]);

        $rule = new ReCaptchaRule;
        $this->assertTrue($rule->passes('token', 'fake-token'));
    }

    #[Test]
    public function user_fillable_does_not_include_privileged_fields()
    {
        $user = new User;
        $fillable = $user->getFillable();

        foreach (['is_active', 'rag', 'status', 'is_free_forever'] as $field) {
            $this->assertNotContains(
                $field,
                $fillable,
                "User::\$fillable must not include '{$field}' (admin-only field)"
            );
        }
    }

    #[Test]
    public function admin_fillable_does_not_include_is_super_admin()
    {
        $admin = new Admin;
        $this->assertNotContains(
            'is_super_admin',
            $admin->getFillable(),
            "Admin::\$fillable must not include 'is_super_admin'"
        );
    }

    #[Test]
    public function user_scope_sort_by_falls_back_to_created_at_for_unknown_column()
    {
        User::factory()->count(3)->create();

        // An injected column name that is not in the allowlist
        $query = User::query()->sortBy('id; DROP TABLE users--');

        // Should not throw and should produce valid SQL using the safe fallback
        $sql = $query->toSql();

        $this->assertStringContainsString('created_at', $sql);
        $this->assertStringNotContainsString('DROP', $sql);
    }

    #[Test]
    public function user_scope_sort_by_rejects_invalid_direction()
    {
        User::factory()->count(2)->create();

        $query = User::query()->sortBy('created_at', 'DESC; DROP TABLE users--');
        $sql = $query->toSql();

        $this->assertStringNotContainsString('DROP', $sql);
    }

    #[Test]
    public function user_json_does_not_expose_guard()
    {
        $user = User::factory()->create();
        $json = $user->toArray();

        $this->assertArrayNotHasKey('guard', $json, 'guard must not be exposed in User JSON output');
    }

    #[Test]
    public function admin_json_does_not_expose_guard()
    {
        $admin = Admin::factory()->create();
        $json = $admin->toArray();

        $this->assertArrayNotHasKey('guard', $json, 'guard must not be exposed in Admin JSON output');
    }
}
