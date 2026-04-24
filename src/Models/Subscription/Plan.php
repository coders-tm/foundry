<?php

namespace Foundry\Models\Subscription;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Foundry\Contracts\Currencyable;
use Foundry\Database\Factories\PlanFactory;
use Foundry\Enum\PlanInterval;
use Foundry\Facades\Currency;
use Foundry\Foundry;
use Foundry\Services\Period;
use Foundry\Concerns\Core;
use Foundry\Concerns\SerializeDate;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Plan extends Model implements Currencyable
{
    use Core, HasSlug, SerializeDate;

    protected $fillable = [
        'label',
        'description',
        'is_active',
        'default_interval',
        'interval',
        'interval_count',
        'is_contract',
        'contract_cycles',
        'allow_freeze',
        'freeze_fee',
        'grace_period_days',
        'price',
        'trial_days',
        'setup_fee',
        'metadata',
    ];

    protected $appends = [
        'feature_lines',
        'price_formatted',
        'interval_label',
        'effective_price',
        'has_trial_period',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_contract' => 'boolean',
        'allow_freeze' => 'boolean',
        'interval_count' => 'integer',
        'contract_cycles' => 'integer',
        'trial_days' => 'integer',
        'grace_period_days' => 'integer',
        'freeze_fee' => 'decimal:2',
        'setup_fee' => 'double',
        'interval' => PlanInterval::class,
        'metadata' => 'json',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Foundry::$subscriptionModel)->active();
    }

    public function getResetDate(?Carbon $dateFrom): Carbon
    {
        $period = new Period($this->interval->value, 1, $dateFrom ?? now());

        return $period->getEndDate();
    }

    /**
     * Calculate the next billing date from a given start date
     */
    public function getNextBillingDate(?Carbon $dateFrom = null): Carbon
    {
        $period = new Period(
            $this->interval->value,
            $this->interval_count,
            $dateFrom ?? now()
        );

        return $period->getEndDate();
    }

    /**
     * Calculate the contract end date from a given start date
     */
    public function getContractEndDate(?Carbon $dateFrom = null): Carbon
    {
        $period = new Period(
            $this->interval->value,
            $this->interval_count,
            $dateFrom ?? now()
        );

        return $period->getEndDate();
    }

    /**
     * Calculate total billing cycles for the contract
     */
    public function getTotalBillingCycles(): ?int
    {
        if (! $this->is_contract) {
            return null; // Unlimited cycles for non-contract plans
        }

        return $this->contract_cycles;
    }

    /**
     * Check if this is a contract plan
     */
    public function isContract(): bool
    {
        return $this->is_contract;
    }

    protected function featureLines(): Attribute
    {
        return Attribute::make(
            get: fn() => ! empty($this->description) ? explode("\n", $this->description) : [],
        );
    }

    protected function priceFormatted(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->formatPrice(),
        );
    }

    protected function intervalLabel(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->formatInterval(),
        );
    }

    protected function effectivePrice(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getEffectivePrice(),
        );
    }

    protected function hasTrialPeriod(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->hasTrial(),
        );
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')->withPivot('value');
    }

    public function syncFeatures(array $items = [])
    {
        $features = collect($items)->mapWithKeys(function ($value, $key) {
            if ($feature = Feature::findBySlug($key)) {
                return [$feature->id => [
                    'value' => $value,
                ]];
            }

            return [$key => null];
        })->filter();

        $this->features()->sync($features->toArray());

        return $this;
    }

    public function isFree(): bool
    {
        return $this->price <= 0;
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    public function getTrialEndDate(?CarbonInterface $startDate = null): ?CarbonInterface
    {
        if (! $this->hasTrial()) {
            return null;
        }

        $start = $startDate ?? now();

        return $start->copy()->addDays($this->trial_days);
    }

    public function getEffectivePrice(?CarbonInterface $currentDate = null): float
    {
        $now = $currentDate ?? now();

        // If in trial period, price is 0
        if ($this->hasTrial()) {
            $trialEnd = $this->getTrialEndDate();
            if ($trialEnd && $now->lte($trialEnd)) {
                return 0;
            }
        }

        // Intro pricing is now handled via coupons
        return (float) ($this->price ?? 0);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function activate(): self
    {
        $this->update(['is_active' => true]);

        return $this;
    }

    public function deactivate(): self
    {
        $this->update(['is_active' => false]);

        return $this;
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('label')
            ->saveSlugsTo('slug')
            ->preventOverwrite();
    }

    public function formatPrice()
    {
        return Currency::format($this->price);
    }

    protected function formatInterval()
    {
        if (! $this->interval) {
            return '';
        }

        $interval = $this->interval->value;
        $intervalCount = $this->interval_count ?? 1;

        if ($intervalCount > 1) {
            return "{$intervalCount} {$interval}s";
        } else {
            return "{$interval}";
        }
    }

    protected function formatAmount($amount)
    {
        return format_amount($amount);
    }

    /**
     * Get freeze fee for this plan (falls back to global config if not set).
     */
    public function getFreezeFee(): float
    {
        return $this->freeze_fee ?? config('foundry.subscription.freeze_fee', 0.00);
    }

    /**
     * Determine if freeze is allowed for this plan.
     */
    public function allowsFreeze(): bool
    {
        // Check plan-specific setting first
        if (! $this->allow_freeze) {
            return false;
        }

        // Fall back to global config
        return config('foundry.subscription.allow_freeze', true);
    }

    /**
     * Get the list of currency fields to be converted.
     */
    public function getCurrencyFields(): array
    {
        return ['price', 'freeze_fee', 'setup_fee'];
    }

    /**
     * Get setup fee for this plan (falls back to global config if not set).
     */
    protected function setupFee(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ?? config('foundry.subscription.setup_fee', 0.00),
        );
    }

    protected static function newFactory()
    {
        return PlanFactory::new();
    }
}
