<?php

namespace Foundry\Providers;

use Foundry\Events\Stripe\WebhookReceived;
use Foundry\Events\SubscriptionCancelled;
use Foundry\Events\SubscriptionPlanChanged;
use Foundry\Events\SubscriptionRenewed;
use Foundry\Events\SupportTicketCreated;
use Foundry\Events\SupportTicketReplyCreated;
use Foundry\Events\UserSubscribed;
use Foundry\Listeners\LoginListener;
use Foundry\Listeners\LogoutListener;
use Foundry\Listeners\SendSignupNotification;
use Foundry\Listeners\SendSupportTicketConfirmation;
use Foundry\Listeners\SendSupportTicketNotification;
use Foundry\Listeners\SendSupportTicketReplyNotification;
use Foundry\Listeners\Subscription\SendSubscriptionRenewNotification;
use Foundry\Mandate\Listeners\ChargeRenewalPayment;
use Foundry\Mandate\Listeners\StripeWebhookListener;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;

class FoundryEventServiceProvider extends EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        SupportTicketCreated::class => [
            SendSupportTicketNotification::class,
            SendSupportTicketConfirmation::class,
        ],
        SupportTicketReplyCreated::class => [
            SendSupportTicketReplyNotification::class,
        ],
        UserSubscribed::class => [
            SendSignupNotification::class,
        ],
        SubscriptionPlanChanged::class => [
        ],
        SubscriptionCancelled::class => [
        ],
        SubscriptionRenewed::class => [
            SendSubscriptionRenewNotification::class,
            ChargeRenewalPayment::class,
        ],
        WebhookReceived::class => [
            StripeWebhookListener::class,
        ],
        Login::class => [
            LoginListener::class,
        ],
        Logout::class => [
            LogoutListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
