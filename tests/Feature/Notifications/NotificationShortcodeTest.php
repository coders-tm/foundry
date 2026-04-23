<?php

namespace Foundry\Tests\Feature\Notifications;

use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\User;
use Foundry\Notifications\SubscriptionCanceledNotification;
use Foundry\Notifications\SubscriptionCancelNotification;
use Foundry\Notifications\SubscriptionDowngradeNotification;
use Foundry\Notifications\SubscriptionExpiredNotification;
use Foundry\Notifications\SubscriptionRenewedNotification;
use Foundry\Notifications\SubscriptionUpgradeNotification;
use Foundry\Notifications\UserSignupNotification;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Facades\Notification;

class NotificationShortcodeTest extends FeatureTestCase
{
    public function test_user_signup_notification()
    {
        $user = User::factory()->create();
        Notification::fake();

        Subscription::factory()->create(['user_id' => $user->id]);

        $user->notify(new UserSignupNotification($user));

        Notification::assertSentTo(
            [$user],
            UserSignupNotification::class,
            function ($notification, $channels) use ($user) {
                $this->assertStringContainsString($user->first_name, $notification->message);
                $this->assertStringContainsString($user->subscription()->plan->label, $notification->message);

                return true;
            }
        );
    }

    public function test_subscription_upgrade_notification()
    {
        $subscription = Subscription::factory()->create();
        Notification::fake();

        $subscription->oldPlan = Plan::factory()->make();

        $subscription->user->notify(new SubscriptionUpgradeNotification($subscription));

        Notification::assertSentTo(
            [$subscription->user],
            SubscriptionUpgradeNotification::class,
            function ($notification, $channels) use ($subscription) {
                $this->assertStringContainsString($subscription->plan->label, $notification->message);

                return true;
            }
        );
    }

    public function test_subscription_renewed_notification()
    {
        $subscription = Subscription::factory()->create();
        Notification::fake();

        $subscription->user->notify(new SubscriptionRenewedNotification($subscription));

        Notification::assertSentTo(
            [$subscription->user],
            SubscriptionRenewedNotification::class,
            function ($notification, $channels) use ($subscription) {
                $this->assertStringContainsString($subscription->plan->label, $notification->message);

                return true;
            }
        );
    }

    public function test_subscription_expired_notification()
    {
        $subscription = Subscription::factory()->create();
        Notification::fake();

        $subscription->user->notify(new SubscriptionExpiredNotification($subscription));

        Notification::assertSentTo(
            [$subscription->user],
            SubscriptionExpiredNotification::class,
            function ($notification, $channels) use ($subscription) {
                $this->assertStringContainsString($subscription->plan->label, $notification->message);

                return true;
            }
        );
    }

    public function test_subscription_downgrade_notification()
    {
        $subscription = Subscription::factory()->create();
        Notification::fake();

        $subscription->user->notify(new SubscriptionDowngradeNotification($subscription));

        Notification::assertSentTo(
            [$subscription->user],
            SubscriptionDowngradeNotification::class,
            function ($notification, $channels) use ($subscription) {
                $this->assertStringContainsString($subscription->plan->label, $notification->message);

                return true;
            }
        );
    }

    public function test_subscription_cancel_notification()
    {
        $subscription = Subscription::factory()->create();
        Notification::fake();

        $subscription->user->notify(new SubscriptionCancelNotification($subscription));

        Notification::assertSentTo(
            [$subscription->user],
            SubscriptionCancelNotification::class,
            function ($notification, $channels) use ($subscription) {
                $this->assertStringContainsString($subscription->plan->label, $notification->message);

                return true;
            }
        );
    }

    public function test_subscription_canceled_notification()
    {
        $subscription = Subscription::factory()->create();
        Notification::fake();

        $subscription->user->notify(new SubscriptionCanceledNotification($subscription));

        Notification::assertSentTo(
            [$subscription->user],
            SubscriptionCanceledNotification::class,
            function ($notification, $channels) use ($subscription) {
                $this->assertStringContainsString($subscription->plan->label, $notification->message);

                return true;
            }
        );
    }
}
