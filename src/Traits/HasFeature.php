<?php

namespace Foundry\Traits;

use Foundry\Exceptions\Plan\FeatureNotFoundException;
use Foundry\Models\Subscription\SubscriptionFeature;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasFeature
{
    /**
     * Get all of the features for the Subscription
     */
    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionFeature::class);
    }

    /**
     * Get all of the usages with max credit for the Subscription
     */
    public function usagesToArray()
    {
        return $this->features->toArray();
    }

    /**
     * Determine if the feature can be used.
     */
    public function canUseFeature(string $featureSlug): bool
    {
        if (! $this->valid()) {
            logger()->info("Subscription is not valid for using feature {$featureSlug}");

            return false;
        }

        /** @var SubscriptionFeature $subscriptionFeature */
        $subscriptionFeature = $this->features()->where('slug', $featureSlug)->first();

        if (! $subscriptionFeature) {
            throw new FeatureNotFoundException;
        }

        return $subscriptionFeature->canUse();
    }

    /**
     * Get how many times the feature has been used.
     */
    public function getFeatureUsage(string $featureSlug): int
    {
        $subscriptionFeature = $this->features()->where('slug', $featureSlug)->first();

        return $subscriptionFeature ? $subscriptionFeature->used : 0;
    }

    /**
     * Get the available uses.
     */
    public function getFeatureRemainings(string $featureSlug): int
    {
        if (! $this->valid()) {
            return 0;
        }

        $subscriptionFeature = $this->features()->where('slug', $featureSlug)->first();

        return $subscriptionFeature ? $subscriptionFeature->remaining : 0;
    }

    /**
     * Get feature value.
     */
    public function getFeatureValue(string $featureSlug)
    {
        $subscriptionFeature = $this->features()->where('slug', $featureSlug)->first();

        return $subscriptionFeature ? $subscriptionFeature->value : null;
    }

    /**
     * Record feature usage.
     */
    public function recordFeatureUsage(string $featureSlug, int $uses = 1, bool $incremental = true)
    {
        $subscriptionFeature = $this->features()->where('slug', $featureSlug)->first();

        if (! $subscriptionFeature) {
            throw new FeatureNotFoundException;
        }

        if ($subscriptionFeature->isBoolean()) {
            return false;
        }

        if ($subscriptionFeature->resetable) {
            $subscriptionFeature->used = 0;
        }

        return $subscriptionFeature->recordUsage($uses, $incremental);
    }

    /**
     * Sync features from plan.
     */
    public function syncFeaturesFromPlan(): void
    {
        if (! $this->plan) {
            return;
        }

        // Ensure plan features are loaded with pivot values
        $this->plan->load('features');

        $planFeatures = $this->plan->features;
        $planFeatureSlugs = $planFeatures->pluck('slug')->toArray();

        // Remove features that are not in the new plan
        $this->features()->whereNotIn('slug', $planFeatureSlugs)->delete();

        // Sync or create features from the new plan, resetting usage
        foreach ($planFeatures as $planFeature) {
            $this->features()->updateOrCreate(['slug' => $planFeature->slug], [
                'label' => $planFeature->label,
                'type' => $planFeature->type,
                'resetable' => $planFeature->resetable,
                'value' => $planFeature->pivot->value,
                'used' => 0, // Reset usage counter
            ]);
        }
    }

    /**
     * Sync or reset usages based on plan.
     */
    public function syncOrResetUsages(): void
    {
        $this->features()->where('resetable', 1)->each(function ($subscriptionFeature) {
            if ($subscriptionFeature->resetable) {
                $subscriptionFeature->used = 0;
            }
            $subscriptionFeature->save();
        });
    }

    /**
     * Reset usages for renewal - respects resetable flag.
     */
    public function resetUsagesForRenewal(): void
    {
        $this->features()->where('resetable', 1)->update([
            'used' => 0,
        ]);

        // Refresh the relationship to avoid stale data if it was already loaded
        $this->unsetRelation('features');
    }

    /**
     * Reset usages based on plan.
     */
    public function resetUsages(): void
    {
        $this->features()->update([
            'used' => 0,
        ]);
    }

    /**
     * Reduce usage.
     */
    public function reduceFeatureUsage(string $featureSlug, int $uses = 1)
    {
        $subscriptionFeature = $this->features()->where('slug', $featureSlug)->first();

        if (is_null($subscriptionFeature)) {
            return null;
        }

        return $subscriptionFeature->reduceUsage($uses);
    }
}
