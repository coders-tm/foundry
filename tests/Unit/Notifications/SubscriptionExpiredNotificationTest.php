<?php

use App\Models\User;
use Foundry\Models\Subscription;
use Foundry\Notifications\SubscriptionExpiredNotification;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Notification;

uses(TestCase::class);

it('sends subscription expired notification', function () {
    Notification::fake();

    $user = User::factory()->create();
    $expiration = now()->subDay();
    $subscription = Subscription::factory()->create(['user_id' => $user->id, 'expires_at' => $expiration]);

    expect($expiration->format('Y-m-d'))->toBe($subscription->expires_at->format('Y-m-d'));

    $notification = new SubscriptionExpiredNotification($subscription);

    Notification::send($user, $notification);

    Notification::assertSentTo(
        $user,
        SubscriptionExpiredNotification::class,
        function ($notification, $channels) use ($subscription) {
            return $notification->subject === $subscription->renderNotification('user:subscription-expired')->subject &&
                $notification->message === $subscription->renderNotification('user:subscription-expired')->content;
        }
    );
});
