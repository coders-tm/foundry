<?php

namespace Foundry\Mandate\Models;

use Foundry\Models\PaymentMethod as PaymentProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PaymentMethod model for storing user payment method references.
 *
 * This model maintains the mapping between application users and their
 * payment methods with external payment providers for auto-renewal purposes.
 */
class PaymentMethod extends Model
{
    use HasUuids;

    protected $table = 'users_payment_methods';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'options',
        'is_default',
    ];

    protected $casts = [
        'options' => 'json',
        'is_default' => 'boolean',
    ];

    /**
     * Scope query to only include default payment methods.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Mark this payment method as the default for the user.
     */
    public function markAsDefault(): self
    {
        static::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);

        return $this;
    }

    /**
     * Get the payment provider gateway associated with this payment method.
     *
     * @return BelongsTo
     */
    public function gateway()
    {
        return $this->belongsTo(PaymentProvider::class, 'provider', 'provider');
    }
}
