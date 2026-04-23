<?php

namespace Foundry\Events;

use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionPlanChanged
{
    use Dispatchable, SerializesModels;

    /**
     * The subscription instance.
     *
     * @var Subscription
     */
    public $subscription;

    /**
     * The old plan instance.
     *
     * @var Plan
     */
    public $oldPlan;

    /**
     * The new plan instance.
     *
     * @var Plan
     */
    public $newPlan;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Subscription $subscription, ?Plan $oldPlan, Plan $newPlan)
    {
        $this->subscription = $subscription;
        $this->oldPlan = $oldPlan;
        $this->newPlan = $newPlan;
    }

    /**
     * Determine if the plan's price has changed.
     *
     * @return bool
     */
    public function hasPriceChanged()
    {
        return $this->oldPlan && $this->oldPlan->price != $this->newPlan->price;
    }

    /**
     * Determine if the plan's interval has changed.
     *
     * @return bool
     */
    public function hasIntervalChanged()
    {
        return $this->oldPlan && ($this->oldPlan->interval != $this->newPlan->interval ||
            $this->oldPlan->interval_count != $this->newPlan->interval_count);
    }

    /**
     * Determine if this is a downgrade (new price < old price).
     *
     * @return bool
     */
    public function isDowngrade()
    {
        return $this->oldPlan && $this->newPlan->price < $this->oldPlan->price;
    }

    /**
     * Determine if this is an upgrade (new price > old price).
     *
     * @return bool
     */
    public function isUpgrade()
    {
        return $this->oldPlan && $this->newPlan->price > $this->oldPlan->price;
    }
}
