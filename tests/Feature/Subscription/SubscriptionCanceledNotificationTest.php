<?php

use Foundry\Models\Subscription;
use Foundry\Models\User;
use Foundry\Notifications\SubscriptionCanceledNotification;
use Foundry\Tests\TestCase;
use Illuminate\Support\Facades\Notification;

uses(TestCase::class);

it('subscription cancellation sends notification', function () {
    Notification::fake();

    $user = User::factory()->create();
    $subscription = Subscription::factory()->canceled()->create(['user_id' => $user->id]);

    $notification = new SubscriptionCanceledNotification($subscription);

    Notification::send($user, $notification);

    Notification::assertSentTo(
        $user,
        SubscriptionCanceledNotification::class,
        function ($notification, $channels) use ($subscription) {
            return $notification->subject === $subscription->renderNotification('user:subscription-canceled')->subject &&
            $notification->message === $subscription->renderNotification('user:subscription-canceled')->content;
        }
    );
});
