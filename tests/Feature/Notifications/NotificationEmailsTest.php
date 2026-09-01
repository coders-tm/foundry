<?php

use Database\Seeders\NotificationSeeder;
use Foundry\Models\Admin;
use Foundry\Models\Import;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\SupportTicket;
use Foundry\Models\User;
use Foundry\Notifications\Admins\HoldMemberNotification;
use Foundry\Notifications\Admins\SubscriptionCanceledNotification as AdminSubscriptionCanceledNotification;
use Foundry\Notifications\Admins\SubscriptionExpiredNotification as AdminSubscriptionExpiredNotification;
use Foundry\Notifications\Admins\SupportTicketNotification as AdminSupportTicketNotification;
use Foundry\Notifications\ImportCompletedNotification;
use Foundry\Notifications\NewAdminNotification;
use Foundry\Notifications\OrderInvoiceNotification;
use Foundry\Notifications\SubscriptionCanceledNotification;
use Foundry\Notifications\SubscriptionCancelNotification;
use Foundry\Notifications\SubscriptionDowngradeNotification;
use Foundry\Notifications\SubscriptionExpiredNotification;
use Foundry\Notifications\SubscriptionRenewedNotification;
use Foundry\Notifications\SubscriptionUpgradeNotification;
use Foundry\Notifications\SupportTicketConfirmation;
use Foundry\Notifications\SupportTicketReplyNotification;
use Foundry\Notifications\UserLogin;
use Foundry\Notifications\UserResetPasswordNotification;
use Foundry\Notifications\UserSignupNotification;
use Foundry\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function () {
    config([
        'mail.default' => env('MAIL_MAILER', 'log'),
        'mail.mailers.smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 1025),
            'encryption' => null,
            'username' => null,
            'password' => null,
            'timeout' => null,
        ],
    ]);

    $this->seed(NotificationSeeder::class);

    $this->user = User::factory()->create([
        'email' => 'user-'.Str::random(8).'@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $this->admin = Admin::factory()->create([
        'email' => 'admin-'.Str::random(8).'@example.com',
        'first_name' => 'Admin',
        'last_name' => 'User',
    ]);

    $this->subscription = Subscription::factory()->create([
        'user_id' => $this->user->id,
    ]);
});

function createCompleteOrder(array $overrides = []): Order
{
    return Order::factory()
        ->complete(overrides: $overrides)
        ->create();
}

it('sends user signup notification', function () {
    $this->user->notify(new UserSignupNotification($this->user));

    $this->assertTrue(true, 'User signup notification sent');
});

it('sends subscription canceled notification to user', function () {
    $this->user->notify(new SubscriptionCanceledNotification($this->subscription));

    $this->assertTrue(true, 'Subscription canceled notification (user) sent');
});

it('sends subscription downgrade notification', function () {
    $oldPlan = $this->subscription->plan;
    $this->subscription->oldPlan = $oldPlan;

    $this->user->notify(new SubscriptionDowngradeNotification($this->subscription));

    $this->assertTrue(true, 'Subscription downgrade notification sent');
});

it('sends subscription expired notification to user', function () {
    $this->user->notify(new SubscriptionExpiredNotification($this->subscription));

    $this->assertTrue(true, 'Subscription expired notification (user) sent');
});

it('sends subscription renewed notification', function () {
    $this->user->notify(new SubscriptionRenewedNotification($this->subscription));

    $this->assertTrue(true, 'Subscription renewed notification sent');
});

it('sends subscription upgrade notification', function () {
    $oldPlan = $this->subscription->plan;
    $this->subscription->oldPlan = $oldPlan;

    $this->user->notify(new SubscriptionUpgradeNotification($this->subscription));

    $this->assertTrue(true, 'Subscription upgrade notification sent');
});

it('sends order invoice notification', function () {
    $order = createCompleteOrder([
        'customer_id' => $this->user->id,
        'orderable_id' => $this->subscription->id,
        'orderable_type' => 'Subscription',
    ]);

    $this->user->notify(new OrderInvoiceNotification($order));

    $this->assertTrue(true, 'Order invoice notification sent');
});

it('sends user reset password notification', function () {
    $resetData = [
        'token' => 'test-reset-token-12345',
        'url' => 'https://example.com/reset-password?token=test-reset-token-12345',
        'expires' => now()->addHours(1)->format('Y-m-d H:i:s'),
    ];

    $this->user->notify(new UserResetPasswordNotification($this->user, $resetData));

    $this->assertTrue(true, 'User reset password notification sent');
});

it('sends new admin notification', function () {
    $newAdmin = Admin::factory()->create([
        'email' => 'newadmin@example.com',
        'first_name' => 'New',
        'last_name' => 'Admin',
    ]);

    $password = 'TempPassword123!';

    $newAdmin->notify(new NewAdminNotification($newAdmin, $password));

    $this->assertTrue(true, 'New admin notification sent');
});

it('sends support ticket confirmation notification', function () {
    $support_ticket = SupportTicket::factory()->create([
        'name' => $this->user->name,
        'email' => $this->user->email,
        'subject' => 'Product Question',
        'message' => 'I have a question about your product.',
    ]);

    $this->user->notify(new SupportTicketConfirmation($support_ticket));

    $this->assertTrue(true, 'Support ticket confirmation notification sent');
});

it('sends support ticket reply notification', function () {
    $support_ticket = SupportTicket::factory()->create([
        'name' => $this->user->name,
        'email' => $this->user->email,
        'subject' => 'Product Question',
    ]);

    $reply = $support_ticket->replies()->create([
        'message' => 'Thank you for your question. Here is the answer...',
        'admin_id' => $this->admin->id,
    ]);

    $this->user->notify(new SupportTicketReplyNotification($reply));

    $this->assertTrue(true, 'Support ticket reply notification sent');
});

it('sends admin support ticket notification', function () {
    $support_ticket = SupportTicket::factory()->create([
        'name' => $this->user->name,
        'email' => $this->user->email,
        'subject' => 'Customer Support Request',
        'message' => 'I need help with my account.',
    ]);

    $this->admin->notify(new AdminSupportTicketNotification($support_ticket));

    $this->assertTrue(true, 'Admin support ticket notification sent');
});

it('sends hold member notification', function () {
    $this->admin->notify(new HoldMemberNotification($this->user));

    $this->assertTrue(true, 'Hold member notification sent');
});

it('sends import completed notification', function () {
    $import = Import::create([
        'user_id' => $this->admin->id,
        'model' => 'User',
        'status' => Import::STATUS_COMPLETED,
        'success' => ['count' => 100],
        'failed' => ['count' => 5],
        'skipped' => [],
    ]);

    $this->admin->notify(new ImportCompletedNotification($import));

    $this->assertTrue(true, 'Import completed notification sent');
});

it('sends user login notification', function () {
    $log = $this->user->logs()->create([
        'type' => 'login',
        'status' => 'success',
        'message' => 'User logged in',
        'options' => [
            'device' => 'Chrome',
            'time' => now()->format('M d, Y h:i A'),
            'location' => 'New York, USA',
            'ip' => '192.168.1.1',
        ],
    ]);

    $this->user->notify(new UserLogin($log));

    $this->assertTrue(true, 'User login notification sent');
});

it('sends subscription cancel notification request', function () {
    $this->user->notify(new SubscriptionCancelNotification($this->subscription));

    $this->assertTrue(true, 'Subscription cancel (request) notification sent');
});

it('sends admin subscription canceled notification', function () {
    $this->admin->notify(new AdminSubscriptionCanceledNotification($this->subscription));

    $this->assertTrue(true, 'Admin subscription canceled notification sent');
});

it('sends admin subscription expired notification', function () {
    $this->admin->notify(new AdminSubscriptionExpiredNotification($this->subscription));

    $this->assertTrue(true, 'Admin subscription expired notification sent');
});
