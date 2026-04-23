<?php

namespace Foundry\Actions\Order;

use Foundry\Models\Order;
use Foundry\Models\Order\Contact;
use Foundry\Models\Order\DiscountLine;
use Foundry\Services\Resource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateOrCreate
{
    /**
     * Execute the action to save or update an order.
     */
    public function execute($resource, ?Order $order = null): Order
    {
        if (is_array($resource)) {
            $resource = new Resource($resource);
        }

        return DB::transaction(function () use ($resource, $order) {
            $resource->merge([
                'customer_id' => $resource->input('customer.id') ?? $resource->customer_id,
            ]);

            $order = $order && $order->exists ? $order : Order::find(has($resource)->id) ?? $order;

            if ($order && $order->exists) {
                $order->update($resource->only((new Order)->getFillable()));
            } else {
                $order = new Order($resource->only((new Order)->getFillable()));
                $order->save();
            }

            // Sync related data
            $this->syncRelations($order, $resource);

            if (! $order->has_due) {
                $order->markAsPaid();
            }

            return $order->fresh();
        });
    }

    /**
     * Sync order relations (items, taxes, discounts, contact).
     */
    public function syncRelations(Order $order, $resource): void
    {
        if (is_array($resource)) {
            $resource = new Resource($resource);
        }

        // Check if we should preserve existing tax calculations
        $preserveTaxCalculations = $resource->boolean('preserve_tax_calculations', false);

        if ($preserveTaxCalculations && $resource->filled('tax_lines') && $resource->filled('tax_total')) {
            $order->fill([
                'sub_total' => $resource->sub_total ?? 0,
                'tax_total' => $resource->tax_total ?? 0,
                'discount_total' => $resource->discount_total ?? 0,
                'grand_total' => $resource->grand_total ?? 0,
            ])->save();

            if ($resource->filled('tax_lines')) {
                $tax_lines = collect($resource->tax_lines);
                $order->tax_lines()->whereNotIn('id', $tax_lines->pluck('id')->filter())->delete();
                $tax_lines->each(function ($tax) use ($order) {
                    $order->tax_lines()->updateOrCreate([
                        'id' => has($tax)->id,
                    ], (array) $tax);
                });
            }
        } else {
            $order->fill([
                'sub_total' => $resource->sub_total ?? $order->sub_total ?? 0,
                'tax_total' => $resource->tax_total ?? $order->tax_total ?? 0,
                'discount_total' => $resource->discount_total ?? $order->discount_total ?? 0,
                'grand_total' => $resource->grand_total ?? $order->grand_total ?? 0,
                'line_items_quantity' => $resource->line_items_quantity ?? $order->line_items_quantity ?? 0,
            ])->save();

            if ($resource->filled('tax_lines')) {
                $tax_lines = collect($resource->tax_lines);
                $order->tax_lines()->whereNotIn('id', $tax_lines->pluck('id')->filter())->delete();
                $tax_lines->each(function ($tax) use ($order) {
                    $order->tax_lines()->updateOrCreate([
                        'id' => has($tax)->id,
                    ], (array) $tax);
                });
            }
        }

        // update order line_items
        if ($resource->filled('line_items')) {
            $order->syncLineItems(collect($resource->input('line_items')));
        }

        // update order contact
        if ($resource->hasAny(['contact.email', 'contact.phone_number'])) {
            if ($resource->boolean('contact.update_customer_profile') && $order->customer) {
                $order->customer->update(Arr::only($resource->contact, ['email', 'phone_number']));
            }

            if ($order->contact) {
                $order->contact->update((new Contact($resource->contact))->toArray());
            } else {
                $order->contact()->save(new Contact($resource->contact));
            }
        }

        // remove discount
        if ($resource->boolean('discount_removed') ?: false) {
            $order->discount()->delete();
        } elseif ($resource->filled('discount')) {
            $order->discount()->delete();
            $order->discount()->save(new DiscountLine($resource->discount));
        }
    }
}
