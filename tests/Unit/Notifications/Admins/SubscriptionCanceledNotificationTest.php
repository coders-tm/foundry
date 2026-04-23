<?php

namespace Foundry\Tests\Unit\Notifications\Admins;

use App\Models\User;
use Foundry\Models\Subscription;
use Foundry\Notifications\Admins\SubscriptionCanceledNotification;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Notification;

class SubscriptionCanceledNotificationTest extends TestCase
{
    public function test_subscription_canceled_notification()
    {
        Notification::fake();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->canceled()->create(['user_id' => $user->id]);

        $notification = new SubscriptionCanceledNotification($subscription);

        Notification::send($user, $notification);

        Notification::assertSentTo(
            $user,
            SubscriptionCanceledNotification::class,
            function ($notification, $channels) use ($subscription) {
                return $notification->subject === $subscription->renderNotification('admin:subscription-cancel')->subject &&
                    $notification->message === $subscription->renderNotification('admin:subscription-cancel')->content;
            }
        );
    }
}
