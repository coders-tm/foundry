<?php

namespace Foundry\Commands;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Events\SubscriptionCancelled;
use Foundry\Foundry;
use Foundry\Models\Log;
use Foundry\Notifications\Admins\SubscriptionCanceledNotification as AdminsSubscriptionCanceledNotification;
use Foundry\Notifications\SubscriptionCanceledNotification;
use Illuminate\Console\Command;

class CheckCanceledSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'foundry:subscriptions-canceled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for canceled subscriptions and send notifications';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $subscriptions = Foundry::$subscriptionModel::query()
            ->canceled()
            ->where('expires_at', '<=', now())
            ->doesntHaveAction('canceled-notification')
            ->hasUser()
            ->with('user');

        foreach ($subscriptions->cursor() as $subscription) {
            try {
                $subscription->attachAction('canceled-notification');
                $subscription->update(['status' => SubscriptionStatus::CANCELED]);

                if ($user = $subscription->user) {
                    event(new SubscriptionCancelled($subscription));

                    $user->notify(new SubscriptionCanceledNotification($subscription));
                    admin_notify(new AdminsSubscriptionCanceledNotification($subscription));

                    $subscription->logs()->create([
                        'type' => 'canceled-notification',
                        'message' => 'Notification for canceled subscriptions has been successfully sent.',
                    ]);
                }
            } catch (\Throwable $e) {
                $subscription->logs()->create([
                    'type' => 'canceled-notification',
                    'status' => Log::STATUS_ERROR,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Expired subscriptions checked and notifications sent.');

        return self::SUCCESS;
    }
}
