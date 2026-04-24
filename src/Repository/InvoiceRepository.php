<?php

namespace Foundry\Repository;

use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Order\LineItem;
use Foundry\Models\Order\TaxLine;
use Illuminate\Support\Collection;

class InvoiceRepository extends BaseRepository
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer',
        'line_items',
        'billing_address',
        'shipping_address',
        'discount',
        'tax_lines',
        'collect_tax',
        'orderable_id',
        'orderable_type',
        'due_date',
        'note',
        'source',
    ];

    /**
     * Create a new repository instance
     */
    public function __construct(array $attributes = [])
    {
        // For InvoiceRepository, we often want to ensure line items are normalized
        if (isset($attributes['line_items']) && is_array($attributes['line_items'])) {
            $attributes['line_items'] = collect($attributes['line_items'])->map(function ($item) {
                // Ensure line-item level discounts are hydrated as DiscountLine objects or arrays
                if (isset($item['discount']) && is_array($item['discount'])) {
                    // This will be handled by LineItem::__construct
                }
                return $item;
            })->toArray();
        }

        parent::__construct($attributes);
    }

    /**
     * Overriding rules if needed, but BaseRepository rules are generally sufficient.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'due_date' => 'nullable',
            'note' => 'nullable|string',
            'source' => 'nullable|string',
            'orderable_id' => 'nullable',
            'orderable_type' => 'nullable',
        ]);
    }
}
