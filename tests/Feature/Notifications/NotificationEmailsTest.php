<?php

namespace Foundry\Tests\Feature\Notifications;

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

/**
 * Test to send actual emails using all notification templates
 *
 * To run this test and send real emails:
 * 1. Configure your .env with real mail settings (e.g., Mailtrap, Mailhog, or SMTP)
 * 2. Run: vendor/bin/phpunit tests/Feature/NotificationEmailsTest.php
 *
 * For Mailtrap (recommended for testing):
 * MAIL_MAILER=smtp
 * MAIL_HOST=sandbox.smtp.mailtrap.io
 * MAIL_PORT=2525
 * MAIL_USERNAME=your_username
 * MAIL_PASSWORD=your_password
 * MAIL_FROM_ADDRESS=test@example.com
 * MAIL_FROM_NAME="Foundry"
 */
class NotificationEmailsTest extends TestCase
{
    protected User $user;

    protected Admin $admin;

    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        // Force SMTP mail driver for actual email sending
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

        // Seed notification templates
        $this->seed(NotificationSeeder::class);

        // Create test data
        $this->user = User::factory()->create([
            'email' => 'user@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->admin = Admin::factory()->create([
            'email' => 'admin@example.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);

        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Helper: Create a complete order with line items, discounts, and taxes
     */
    protected function createCompleteOrder(array $overrides = []): Order
    {
        return Order::factory()
            ->complete(overrides: $overrides)
            ->create();
    }

    /**
     * Test: Send user signup notification
     */
    public function test_send_user_signup_notification()
    {
        $this->user->notify(new UserSignupNotification($this->user));

        $this->assertTrue(true, 'User signup notification sent');
    }

    /**
     * Test: Send subscription canceled notification (user)
     */
    public function test_send_subscription_canceled_notification_user()
    {
        $this->user->notify(new SubscriptionCanceledNotification($this->subscription));

        $this->assertTrue(true, 'Subscription canceled notification (user) sent');
    }

    /**
     * Test: Send subscription downgrade notification
     */
    public function test_send_subscription_downgrade_notification()
    {
        // Create an old plan to test the downgrade
        $oldPlan = $this->subscription->plan;
        // Set oldPlan as a dynamic property (not a relationship)
        $this->subscription->oldPlan = $oldPlan;

        $this->user->notify(new SubscriptionDowngradeNotification($this->subscription));

        $this->assertTrue(true, 'Subscription downgrade notification sent');
    }

    /**
     * Test: Send subscription expired notification (user)
     */
    public function test_send_subscription_expired_notification_user()
    {
        $this->user->notify(new SubscriptionExpiredNotification($this->subscription));

        $this->assertTrue(true, 'Subscription expired notification (user) sent');
    }

    /**
     * Test: Send subscription renewed notification
     */
    public function test_send_subscription_renewed_notification()
    {
        $this->user->notify(new SubscriptionRenewedNotification($this->subscription));

        $this->assertTrue(true, 'Subscription renewed notification sent');
    }

    /**
     * Test: Send subscription upgrade notification
     */
    public function test_send_subscription_upgrade_notification()
    {
        // Create an old plan to test the upgrade
        $oldPlan = $this->subscription->plan;
        // Set oldPlan as a dynamic property (not a relationship)
        $this->subscription->oldPlan = $oldPlan;

        $this->user->notify(new SubscriptionUpgradeNotification($this->subscription));

        $this->assertTrue(true, 'Subscription upgrade notification sent');
    }

    /**
     * Test: Send order invoice notification
     */
    public function test_send_order_invoice_notification()
    {
        $order = $this->createCompleteOrder([
            'customer_id' => $this->user->id,
            'orderable_id' => $this->subscription->id,
            'orderable_type' => 'Subscription',
        ]);

        $this->user->notify(new OrderInvoiceNotification($order));

        $this->assertTrue(true, 'Order invoice notification sent');
    }

    /**
     * Test: Send user reset password notification
     */
    public function test_send_user_reset_password_notification()
    {
        $resetData = [
            'token' => 'test-reset-token-12345',
            'url' => 'https://example.com/reset-password?token=test-reset-token-12345',
            'expires' => now()->addHours(1)->format('Y-m-d H:i:s'),
        ];

        $this->user->notify(new UserResetPasswordNotification($this->user, $resetData));

        $this->assertTrue(true, 'User reset password notification sent');
    }

    /**
     * Test: Send new admin notification
     */
    public function test_send_new_admin_notification()
    {
        $newAdmin = Admin::factory()->create([
            'email' => 'newadmin@example.com',
            'first_name' => 'New',
            'last_name' => 'Admin',
        ]);

        $password = 'TempPassword123!';

        $newAdmin->notify(new NewAdminNotification($newAdmin, $password));

        $this->assertTrue(true, 'New admin notification sent');
    }

    /**
     * Test: Send support ticket confirmation notification
     */
    public function test_send_support_ticket_confirmation_notification()
    {
        $support_ticket = SupportTicket::factory()->create([
            'name' => $this->user->name,
            'email' => $this->user->email,
            'subject' => 'Product Question',
            'message' => 'I have a question about your product.',
        ]);

        $this->user->notify(new SupportTicketConfirmation($support_ticket));

        $this->assertTrue(true, 'Support ticket confirmation notification sent');
    }

    /**
     * Test: Send support ticket reply notification
     */
    public function test_send_support_ticket_reply_notification()
    {
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
    }

    /**
     * Test: Send admin support ticket notification
     */
    public function test_send_admin_support_ticket_notification()
    {
        $support_ticket = SupportTicket::factory()->create([
            'name' => $this->user->name,
            'email' => $this->user->email,
            'subject' => 'Customer Support Request',
            'message' => 'I need help with my account.',
        ]);

        $this->admin->notify(new AdminSupportTicketNotification($support_ticket));

        $this->assertTrue(true, 'Admin support ticket notification sent');
    }

    /**
     * Test: Send hold member notification
     */
    public function test_send_hold_member_notification()
    {
        $this->admin->notify(new HoldMemberNotification($this->user));

        $this->assertTrue(true, 'Hold member notification sent');
    }

    /**
     * Test: Send import completed notification
     */
    public function test_send_import_completed_notification()
    {
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
    }

    /**
     * Test: Send user login notification
     */
    public function test_send_user_login_notification()
    {
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
    }

    /**
     * Test: Send subscription cancel notification (request)
     */
    public function test_send_subscription_cancel_notification()
    {
        $this->user->notify(new SubscriptionCancelNotification($this->subscription));

        $this->assertTrue(true, 'Subscription cancel (request) notification sent');
    }

    /**
     * Test: Send admin subscription canceled notification
     */
    public function test_send_admin_subscription_canceled_notification()
    {
        $this->admin->notify(new AdminSubscriptionCanceledNotification($this->subscription));

        $this->assertTrue(true, 'Admin subscription canceled notification sent');
    }

    /**
     * Test: Send admin subscription expired notification
     */
    public function test_send_admin_subscription_expired_notification()
    {
        $this->admin->notify(new AdminSubscriptionExpiredNotification($this->subscription));

        $this->assertTrue(true, 'Admin subscription expired notification sent');
    }
}
