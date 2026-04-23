<?php

namespace Foundry\Tests\Notifications\Admins;

use App\Models\User;
use Foundry\Models\Subscription;
use Foundry\Notifications\Admins\SubscriptionExpiredNotification;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Notification;

class SubscriptionExpiredNotificationTest extends TestCase
{
    public function test_notification_construct()
    {
        Notification::fake();

        $user = User::factory()->create();
        $expiration = now()->subDay();
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'expires_at' => $expiration]);

        $this->assertEquals($subscription->expires_at->format('Y-m-d'), $expiration->format('Y-m-d'));

        $notification = new SubscriptionExpiredNotification($subscription);

        Notification::send($user, $notification);

        Notification::assertSentTo(
            $user,
            SubscriptionExpiredNotification::class,
            function ($notification, $channels) use ($subscription) {
                return $notification->subject === $subscription->renderNotification('admin:subscription-expired')->subject &&
                    $notification->message === $subscription->renderNotification('admin:subscription-expired')->content;
            }
        );
    }
}
