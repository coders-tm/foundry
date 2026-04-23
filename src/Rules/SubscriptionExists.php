<?php

namespace Foundry\Rules;

use Foundry\Models\Subscription\Plan;
use Illuminate\Contracts\Validation\Rule;

class SubscriptionExists implements Rule
{
    public function passes($attribute, $value)
    {
        return Plan::find($value)->subscriptions()->count() === 0;
    }

    public function message()
    {
        return 'The plan cannot be deleted because it has active subscriptions.';
    }
}
