<?php

namespace Foundry\Repository;

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

    public function total()
    {
        return $this->grand_total;
    }
}
