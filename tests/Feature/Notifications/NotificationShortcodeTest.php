<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class);

it('sends user signup notification', function () {
    $user = \Foundry\Models\User::factory()->create();
    \Illuminate\Support\Facades\Notification::fake();

    \Foundry\Models\Subscription::factory()->create(['user_id' => $user->id]);

    $user->notify(new \Foundry\Notifications\UserSignupNotification($user));

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$user],
        \Foundry\Notifications\UserSignupNotification::class,
        function ($notification, $channels) use ($user) {
            $this->assertStringContainsString($user->first_name, $notification->message);
            $this->assertStringContainsString($user->subscription()->plan->label, $notification->message);
            return true;
        }
    );
});

it('sends subscription upgrade notification', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create();
    \Illuminate\Support\Facades\Notification::fake();

    $subscription->oldPlan = \Foundry\Models\Subscription\Plan::factory()->make();

    $subscription->user->notify(new \Foundry\Notifications\SubscriptionUpgradeNotification($subscription));

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$subscription->user],
        \Foundry\Notifications\SubscriptionUpgradeNotification::class,
        function ($notification, $channels) use ($subscription) {
            $this->assertStringContainsString($subscription->plan->label, $notification->message);
            return true;
        }
    );
});

it('sends subscription renewed notification', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create();
    \Illuminate\Support\Facades\Notification::fake();

    $subscription->user->notify(new \Foundry\Notifications\SubscriptionRenewedNotification($subscription));

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$subscription->user],
        \Foundry\Notifications\SubscriptionRenewedNotification::class,
        function ($notification, $channels) use ($subscription) {
            $this->assertStringContainsString($subscription->plan->label, $notification->message);
            return true;
        }
    );
});

it('sends subscription expired notification', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create();
    \Illuminate\Support\Facades\Notification::fake();

    $subscription->user->notify(new \Foundry\Notifications\SubscriptionExpiredNotification($subscription));

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$subscription->user],
        \Foundry\Notifications\SubscriptionExpiredNotification::class,
        function ($notification, $channels) use ($subscription) {
            $this->assertStringContainsString($subscription->plan->label, $notification->message);
            return true;
        }
    );
});

it('sends subscription downgrade notification', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create();
    \Illuminate\Support\Facades\Notification::fake();

    $subscription->user->notify(new \Foundry\Notifications\SubscriptionDowngradeNotification($subscription));

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$subscription->user],
        \Foundry\Notifications\SubscriptionDowngradeNotification::class,
        function ($notification, $channels) use ($subscription) {
            $this->assertStringContainsString($subscription->plan->label, $notification->message);
            return true;
        }
    );
});

it('sends subscription cancel notification', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create();
    \Illuminate\Support\Facades\Notification::fake();

    $subscription->user->notify(new \Foundry\Notifications\SubscriptionCancelNotification($subscription));

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$subscription->user],
        \Foundry\Notifications\SubscriptionCancelNotification::class,
        function ($notification, $channels) use ($subscription) {
            $this->assertStringContainsString($subscription->plan->label, $notification->message);
            return true;
        }
    );
});

it('sends subscription canceled notification', function () {
    $subscription = \Foundry\Models\Subscription::factory()->create();
    \Illuminate\Support\Facades\Notification::fake();

    $subscription->user->notify(new \Foundry\Notifications\SubscriptionCanceledNotification($subscription));

    \Illuminate\Support\Facades\Notification::assertSentTo(
        [$subscription->user],
        \Foundry\Notifications\SubscriptionCanceledNotification::class,
        function ($notification, $channels) use ($subscription) {
            $this->assertStringContainsString($subscription->plan->label, $notification->message);
            return true;
        }
    );
});
