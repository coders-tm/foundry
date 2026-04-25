<?php

namespace Foundry\Models\Order;

use Foundry\Contracts\Currencyable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxLine extends Model implements Currencyable
{
    /**
     * Get the list of currency fields to be converted.
     *
     * @return array Field names that contain currency amounts
     */
    public function getCurrencyFields(): array
    {
        return ['amount'];
    }

    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'label',
        'rate',
        'amount',
        'type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate' => 'decimal:2',
    ];

    const TYPE_NORMAL = 'normal';

    const TYPE_COMPOUND = 'compound';

    protected $hidden = [
        'taxable_type',
        'taxable_id',
    ];

    public function taxable()
    {
        return $this->morphTo();
    }
}
