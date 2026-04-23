<?php

namespace Foundry\AutoRenewal\Models;

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
    ];

    protected $casts = [
        'options' => 'json',
    ];

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
