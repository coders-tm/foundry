<?php

namespace Foundry\Traits\Subscription;

use Carbon\Carbon;
use Foundry\Contracts\SubscriptionStatus;
use Foundry\Foundry;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription.
     *
     * @param  Plan  $plan
     * @return Subscription
     */
    public function newSubscription(string $type, $plan, array $metadata = [])
    {
        if (empty($plan)) {
            throw new InvalidArgumentException('Please provide a plan when new subscription.');
        }

        if ($plan instanceof Collection) {
            $plan = $plan->first();
        }

        // If plan is not already a model instance, find it by ID
        if (! $plan instanceof Foundry::$planModel && ! is_object($plan)) {
            $plan = Foundry::$planModel::find($plan);
        }

        if (! $plan) {
            throw new InvalidArgumentException('Invalid plan provided. Please ensure the plan exists.');
        }

        // Calculate trial period
        $trial = new Period(
            'day',
            $plan->trial_days,
            Carbon::now()
        );

        // Determine the start date for the regular billing period
        $billingStartDate = $trial->getEndDate();
        $isTrial = $plan->trial_days > 0;

        // Calculate the regular billing period
        $period = new Period(
            $plan->interval->value,
            $plan->interval_count,
            $billingStartDate
        );

        return new Foundry::$subscriptionModel([
            'type' => $type,
            'plan_id' => $plan->getKey(),
            'status' => $isTrial ? SubscriptionStatus::TRIALING : SubscriptionStatus::PENDING,
            'trial_ends_at' => $isTrial ? $trial->getEndDate() : null,
            'starts_at' => $isTrial ? now() : $period->getStartDate(),
            'expires_at' => $isTrial ? $trial->getEndDate() : $period->getEndDate(),
            'billing_interval' => $plan->interval->value,
            'billing_interval_count' => $plan->interval_count,
            'total_cycles' => $plan->contract_cycles,
            $this->getForeignKey() => $this->getKey(),
        ]);
    }

    /**
     * Determine if the Stripe model is on trial.
     *
     * @param  string  $type
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrial($type = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return ! $plan || $subscription->hasPlan($plan);
    }

    /**
     * Determine if the model is in an intro pricing period via coupon.
     *
     * @param  string  $type
     * @param  string|null  $plan
     * @return bool
     */
    public function onIntroPricing($type = 'default', $plan = null)
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->coupon) {
            return false;
        }

        $coupon = $subscription->coupon;

        // Check if this is an intro pricing coupon
        if (! $coupon->is_intro_pricing) {
            return false;
        }

        // Check if coupon can still be applied
        if (! $coupon->canApplyToPlan($subscription->plan_id)) {
            return false;
        }

        // Check based on coupon duration type
        switch ($coupon->duration->value) {
            case 'once':
                return $subscription->invoices()->count() <= 1;

            case 'repeating':
                $monthsElapsed = $subscription->starts_at->diffInMonths(Carbon::now());

                return $monthsElapsed < $coupon->duration_in_months;

            case 'forever':
                return true;

            default:
                return false;
        }
    }

    /**
     * Determine if the Stripe model's trial has ended.
     *
     * @param  string  $type
     * @param  string|null  $plan
     * @return bool
     */
    public function onTrialExpired($type = 'default', $plan = null)
    {
        if (func_num_args() === 0 && $this->hasExpiredGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->onTrialExpired()) {
            return false;
        }

        return ! $plan || $subscription->hasPlan($plan);
    }

    /**
     * Determine if the Stripe model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter the given query for generic trials.
     *
     * @param  Builder  $query
     * @return void
     */
    public function scopeOnGenericTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the Stripe model's "generic" trial at the model level has expired.
     *
     * @return bool
     */
    public function hasExpiredGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter the given query for expired generic trials.
     *
     * @param  Builder  $query
     * @return void
     */
    public function scopeHasExpiredGenericTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
    }

    /**
     * Get the ending date of the trial.
     *
     * @param  string  $type
     * @return \Illuminate\Support\Carbon|null
     */
    public function trialEndsAt($type = 'default')
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return $this->trial_ends_at;
        }

        if ($subscription = $this->subscription($type)) {
            return $subscription->trial_ends_at;
        }

        return $this->trial_ends_at;
    }

    /**
     * Determine if the Stripe model has a given subscription.
     *
     * @param  string  $type
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed($type = 'default', $plan = null)
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return ! $plan || $subscription->hasPlan($plan);
    }

    /**
     * Get a subscription instance by type.
     *
     * @param  string  $type
     * @return Subscription|null
     */
    public function subscription($type = 'default')
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Foundry::$subscriptionModel, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the customer's subscription has an incomplete payment.
     *
     * @param  string  $type
     * @return bool
     */
    public function hasIncompletePayment($type = 'default')
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->hasIncompletePayment();
        }

        return false;
    }
}
