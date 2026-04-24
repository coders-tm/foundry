<?php

namespace Foundry\Actions\Subscription;

use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Subscription;

class GenerateSubscriptionInvoice
{
    /**
     * Generate a new invoice for the subscription.
     *
     * @param  bool  $start  Whether this is a start invoice
     * @param  bool  $force  Force generation even on trial
     * @return mixed|null
     */
    public function execute(Subscription $subscription, bool $start = false, bool $force = false)
    {
        if ($subscription->is_free_forever || ($subscription->plan && $subscription->plan->price <= 0)) {
            $subscription->status = SubscriptionStatus::ACTIVE;
            $subscription->next_plan = null;
            $subscription->is_downgrade = false;
            $subscription->save();

            return null;
        }

        if (! $subscription->upcomingInvoice()) {
            return null;
        }

        $upcomingInvoice = $subscription->upcomingInvoice($start);

        if (! $upcomingInvoice) {
            return null;
        }

        if ($subscription->onTrial() && ! $force) {
            return null;
        }

        $data = $upcomingInvoice->toArray();

        // If the latest invoice is still pending payment, update it instead of creating a new one
        if (($latestInvoice = $subscription->latestInvoice) && $latestInvoice->isPendingPayment()) {
            $order = $latestInvoice->update($data);
        } else {
            $order = Foundry::$orderModel::create($data);
        }

        if ($order->is_paid) {
            $subscription->status = SubscriptionStatus::ACTIVE;
        } else {
            $order->markAsOpen();
            $subscription->status = $start ? SubscriptionStatus::PENDING : SubscriptionStatus::ACTIVE;
        }

        $subscription->next_plan = null;
        $subscription->is_downgrade = false;

        $subscription->save();

        return $order;
    }
}
