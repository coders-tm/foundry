<?php

use App\Models\User;
use Foundry\Models\Subscription;
use Foundry\Notifications\Admins\SubscriptionCanceledNotification;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Notification;

uses(TestCase::class);

it('sends admin subscription canceled notification', function () {
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
});
