<?php

uses(\Foundry\Tests\TestCase::class);

it('subscription cancellation sends notification', function () {
    \Illuminate\Support\Facades\Notification::fake();
    
    $user = \Foundry\Models\User::factory()->create();
    $subscription = \Foundry\Models\Subscription::factory()->canceled()->create(['user_id' => $user->id]);
    
    $notification = new \Foundry\Notifications\SubscriptionCanceledNotification($subscription);
    
    \Illuminate\Support\Facades\Notification::send($user, $notification);
    
    \Illuminate\Support\Facades\Notification::assertSentTo(
    $user,
    \Foundry\Notifications\SubscriptionCanceledNotification::class,
    function ($notification, $channels) use ($subscription) {
    return $notification->subject === $subscription->renderNotification('user:subscription-canceled')->subject &&
    $notification->message === $subscription->renderNotification('user:subscription-canceled')->content;
    }
    );
});
