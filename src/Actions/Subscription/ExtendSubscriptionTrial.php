<?php

namespace Foundry\Actions\Subscription;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Models\Subscription;

class ExtendSubscriptionTrial
{
    /**
     * Extend an existing subscription's trial period.
     */
    public function extendTrial(Subscription $subscription, CarbonInterface $date): Subscription
    {
        if (! $date->isFuture()) {
            throw new \InvalidArgumentException("Extending a subscription's trial requires a date in the future.");
        }

        $subscription->trial_ends_at = $date;
        $subscription->save();

        return $subscription;
    }

    /**
     * Specify the number of days for the trial.
     */
    public function trialDays(Subscription $subscription, int $trialDays): Subscription
    {
        $subscription->trial_ends_at = Carbon::now()->addDays($trialDays);
        $subscription->status = SubscriptionStatus::TRIALING;
        $subscription->save();

        return $subscription;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @param  Carbon|CarbonInterface|string  $trialUntil
     */
    public function trialUntil(Subscription $subscription, $trialUntil): Subscription
    {
        if (is_string($trialUntil)) {
            $trialUntil = Carbon::parse($trialUntil);
        } elseif ($trialUntil instanceof \DateTimeInterface && ! $trialUntil instanceof Carbon) {
            $trialUntil = Carbon::instance($trialUntil);
        }

        $subscription->trial_ends_at = $trialUntil;
        $subscription->status = SubscriptionStatus::TRIALING;
        $subscription->save();

        return $subscription;
    }

    /**
     * Force the trial to end immediately.
     */
    public function endTrial(Subscription $subscription): Subscription
    {
        if (is_null($subscription->trial_ends_at)) {
            return $subscription;
        }

        $subscription->trial_ends_at = null;
        $subscription->save();

        return $subscription;
    }
}
