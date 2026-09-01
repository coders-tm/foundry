<?php

uses(Foundry\Tests\TestCase::class);

it('sends admin subscription expired notification', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $user = \App\Models\User::factory()->create();
    $expiration = now()->subDay();
    $subscription = \Foundry\Models\Subscription::factory()->create(['user_id' => $user->id, 'expires_at' => $expiration]);

    $this->assertEquals($subscription->expires_at->format('Y-m-d'), $expiration->format('Y-m-d'));

    $notification = new \Foundry\Notifications\Admins\SubscriptionExpiredNotification($subscription);

    \Illuminate\Support\Facades\Notification::send($user, $notification);

    \Illuminate\Support\Facades\Notification::assertSentTo(
        $user,
        \Foundry\Notifications\Admins\SubscriptionExpiredNotification::class,
        function ($notification, $channels) use ($subscription) {
            return $notification->subject === $subscription->renderNotification('admin:subscription-expired')->subject &&
                $notification->message === $subscription->renderNotification('admin:subscription-expired')->content;
        }
    );
});
