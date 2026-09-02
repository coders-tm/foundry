<?php

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

uses(FeatureTestCase::class);

it('sends user signup notification', function () {
    $user = User::factory()->create();
    Notification::fake();

    Subscription::factory()->create(['user_id' => $user->id]);

    $user->notify(new UserSignupNotification($user));

    Notification::assertSentTo(
        [$user],
        UserSignupNotification::class,
        function ($notification, $channels) use ($user) {
            expect($notification->message)->toContain($user->first_name);
            expect($notification->message)->toContain($user->subscription()->plan->label);

            return true;
        }
    );
});

it('sends subscription upgrade notification', function () {
    $subscription = Subscription::factory()->create();
    Notification::fake();

    $subscription->oldPlan = Plan::factory()->make();

    $subscription->user->notify(new SubscriptionUpgradeNotification($subscription));

    Notification::assertSentTo(
        [$subscription->user],
        SubscriptionUpgradeNotification::class,
        function ($notification, $channels) use ($subscription) {
            expect($notification->message)->toContain($subscription->plan->label);

            return true;
        }
    );
});

it('sends subscription renewed notification', function () {
    $subscription = Subscription::factory()->create();
    Notification::fake();

    $subscription->user->notify(new SubscriptionRenewedNotification($subscription));

    Notification::assertSentTo(
        [$subscription->user],
        SubscriptionRenewedNotification::class,
        function ($notification, $channels) use ($subscription) {
            expect($notification->message)->toContain($subscription->plan->label);

            return true;
        }
    );
});

it('sends subscription expired notification', function () {
    $subscription = Subscription::factory()->create();
    Notification::fake();

    $subscription->user->notify(new SubscriptionExpiredNotification($subscription));

    Notification::assertSentTo(
        [$subscription->user],
        SubscriptionExpiredNotification::class,
        function ($notification, $channels) use ($subscription) {
            expect($notification->message)->toContain($subscription->plan->label);

            return true;
        }
    );
});

it('sends subscription downgrade notification', function () {
    $subscription = Subscription::factory()->create();
    Notification::fake();

    $subscription->user->notify(new SubscriptionDowngradeNotification($subscription));

    Notification::assertSentTo(
        [$subscription->user],
        SubscriptionDowngradeNotification::class,
        function ($notification, $channels) use ($subscription) {
            expect($notification->message)->toContain($subscription->plan->label);

            return true;
        }
    );
});

it('sends subscription cancel notification', function () {
    $subscription = Subscription::factory()->create();
    Notification::fake();

    $subscription->user->notify(new SubscriptionCancelNotification($subscription));

    Notification::assertSentTo(
        [$subscription->user],
        SubscriptionCancelNotification::class,
        function ($notification, $channels) use ($subscription) {
            expect($notification->message)->toContain($subscription->plan->label);

            return true;
        }
    );
});

it('sends subscription canceled notification', function () {
    $subscription = Subscription::factory()->create();
    Notification::fake();

    $subscription->user->notify(new SubscriptionCanceledNotification($subscription));

    Notification::assertSentTo(
        [$subscription->user],
        SubscriptionCanceledNotification::class,
        function ($notification, $channels) use ($subscription) {
            expect($notification->message)->toContain($subscription->plan->label);

            return true;
        }
    );
});
