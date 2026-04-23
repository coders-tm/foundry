<?php

namespace Foundry\Models\Subscription;

use Foundry\Database\Factories\SubscriptionFeatureFactory;
use Foundry\Models\Subscription;
use Foundry\Traits\SerializeDate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionFeature extends Model
{
    use HasFactory, HasUuids, SerializeDate;

    protected $table = 'subscription_features';

    protected $fillable = [
        'subscription_id',
        'slug',
        'label',
        'type',
        'resetable',
        'value',
        'used',
    ];

    protected $casts = [
        'resetable' => 'boolean',
    ];

    protected $appends = [
        'remaining',
    ];

    /**
     * Get the subscription that owns the subscription feature.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if the feature is boolean type.
     */
    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    /**
     * Get the remaining usage.
     */
    public function getRemainingAttribute(): int
    {
        return $this->value - $this->used;
    }

    /**
     * Check if the feature can be used.
     */
    public function canUse(): bool
    {
        if ($this->isBoolean()) {
            return $this->value === 1;
        }

        // If feature value is explicitly set to -1, it can be used
        if ($this->value === -1) {
            return true;
        }

        // Check for available uses
        return $this->remaining > 0;
    }

    /**
     * Record usage for this feature.
     */
    public function recordUsage(int $uses = 1, bool $incremental = true): self
    {
        if ($this->isBoolean()) {
            return $this;
        }

        $this->used = ($incremental ? $this->used + $uses : $uses);
        $this->save();

        return $this;
    }

    /**
     * Reduce usage for this feature.
     */
    public function reduceUsage(int $uses = 1): self
    {
        $this->used = max($this->used - $uses, 0);
        $this->save();

        return $this;
    }

    public function resetUsage(): self
    {
        $this->used = 0;
        $this->save();

        return $this;
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return SubscriptionFeatureFactory::new();
    }
}
