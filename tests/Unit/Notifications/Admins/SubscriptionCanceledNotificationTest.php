<?php

uses(Foundry\Tests\TestCase::class);

it('sends admin subscription canceled notification', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $user = \App\Models\User::factory()->create();
    $subscription = \Foundry\Models\Subscription::factory()->canceled()->create(['user_id' => $user->id]);

    $notification = new \Foundry\Notifications\Admins\SubscriptionCanceledNotification($subscription);

    \Illuminate\Support\Facades\Notification::send($user, $notification);

    \Illuminate\Support\Facades\Notification::assertSentTo(
        $user,
        \Foundry\Notifications\Admins\SubscriptionCanceledNotification::class,
        function ($notification, $channels) use ($subscription) {
            return $notification->subject === $subscription->renderNotification('admin:subscription-cancel')->subject &&
                $notification->message === $subscription->renderNotification('admin:subscription-cancel')->content;
        }
    );
});
