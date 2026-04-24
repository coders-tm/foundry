<?php

namespace Foundry\Concerns\Subscription;

use Foundry\Foundry;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Arr;

trait ManagesCustomer
{
    public function isSubscribed(): bool
    {
        return $this->onTrial() || $this->subscribed();
    }

    public function hasCancelled(): bool
    {
        return $this->subscribed() ? $this->subscription()->canceled() : false;
    }

    public function canUseFeature(string $featureSlug): ?bool
    {
        try {
            return $this->subscription()?->canUseFeature($featureSlug);
        } catch (\Throwable $e) {
            logger()->error('Error checking feature usage: '.$e->getMessage());

            return false;
        }
    }

    public function recordFeatureUsage(string $featureSlug, int $uses = 1, bool $incremental = true)
    {
        try {
            return $this->subscription()?->recordFeatureUsage($featureSlug, $uses, $incremental);
        } catch (\Throwable $e) {
            logger()->error('Error recording feature usage: '.$e->getMessage());

            return false;
        }
    }

    public function reduceFeatureUsage(string $featureSlug, int $uses = 1)
    {
        try {
            return $this->subscription()?->reduceFeatureUsage($featureSlug, $uses);
        } catch (\Throwable $e) {
            logger()->error('Error reducing feature usage: '.$e->getMessage());

            return false;
        }
    }

    public function getFeatureRemainings(string $featureSlug): int
    {
        try {
            return $this->subscription()?->getFeatureRemainings($featureSlug) ?? 0;
        } catch (\Throwable $e) {
            logger()->error('Error getting feature remainings: '.$e->getMessage());

            return 0;
        }
    }

    public function getFeatureValue(string $featureSlug, $default = null)
    {
        try {
            return $this->subscription()?->getFeatureValue($featureSlug) ?? $default;
        } catch (\Throwable $e) {
            logger()->error('Error getting feature value: '.$e->getMessage());

            return $default;
        }
    }

    /**
     * Get the Stripe supported currency used by the customer.
     *
     * @return string
     */
    public function preferredCurrency()
    {
        return config('app.currency');
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Foundry::formatAmount($amount, $this->preferredCurrency());
    }

    /**
     * Get all of the invoices for the User
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Foundry::$orderModel, 'customer_id');
    }

    /**
     * Get the latest invoices for the User
     */
    public function latestInvoice(): HasOne
    {
        return $this->hasOne(Foundry::$orderModel, 'customer_id')->latest();
    }

    protected function billingAddress(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone_number,
            'address' => Arr::only($this->address->toArray(), [
                'line1',
                'line2',
                'city',
                'state',
                'postal_code',
            ]),
        ];
    }

    /**
     * The plan that belong to the User
     */
    public function plan(): HasOneThrough
    {
        return $this->hasOneThrough(
            Foundry::$planModel,
            Foundry::$subscriptionModel,
            $this->getForeignKey(),
            'id',
            'id',
            (new Foundry::$planModel)->getForeignKey()
        )->orderByDesc('created_at');
    }
}
