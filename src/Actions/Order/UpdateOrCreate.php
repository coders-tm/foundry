<?php

namespace Foundry\Actions\Order;

use Foundry\Foundry;
use Foundry\Models\Order;
use Foundry\Models\Order\Contact;
use Foundry\Models\Order\DiscountLine;
use Foundry\Repository\OrderRepository;
use Foundry\Services\Resource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateOrCreate
{
    /**
     * Handle the action to save or update an order.
     *
     * @return Order
     */
    public function __invoke($resource, $options = [], ?Order $order = null)
    {
        return $this->execute($resource, $options, $order);
    }

    /**
     * Execute the action to save or update an order.
     *
     * @return Order
     */
    public function execute($resource, $options = [], ?Order $order = null)
    {
        if (is_array($resource)) {
            $resource = new Resource($resource);
        }

        return DB::transaction(function () use ($resource, $order, $options) {
            $resource->merge([
                'customer_id' => $resource->input('customer.id') ?? $resource->customer_id,
            ]);

            $fillableData = $resource->only((new Foundry::$orderModel)->getFillable());

            if ($order && $order->exists) {
                $order->fill($fillableData);
            } else {
                $order = new Foundry::$orderModel($fillableData);
            }

            // update order with line items, taxes, discounts, and contact information
            $this->save($order, $resource, $options);

            if (! $order->has_due) {
                $order->markAsPaid();
            }

            return $order->fresh();
        });
    }

    /**
     * Sync order relations (items, taxes, discounts, contact).
     */
    public function save(Order $order, $resource, array $options = []): void
    {
        if (is_array($resource)) {
            $resource = new Resource($resource);
        }

        // Check if we should preserve existing tax calculations
        $preserveCalculations = $options['preserve_calculations'] ?? false;
        $shouldRecalculate = ! $preserveCalculations && (
            $resource->hasAny(['line_items', 'tax_lines', 'discount', 'discount_removed']) ||
            ($order->exists && $order->line_items()->exists() && $resource->hasAny(['collect_tax', 'billing_address']))
        );

        if ($shouldRecalculate) {
            $repository = new OrderRepository($resource->input());
            $order->fill([
                'sub_total' => $repository->sub_total,
                'tax_total' => $repository->tax_total,
                'discount_total' => $repository->discount_total,
                'grand_total' => $repository->grand_total,
                'line_items_quantity' => $repository->line_items_quantity,
            ])->save();
        } else {
            $order->save();
        }

        if ($resource->filled('tax_lines')) {
            $tax_lines = collect($resource->tax_lines);
            $order->tax_lines()->whereNotIn('id', $tax_lines->pluck('id')->filter())->delete();
            $tax_lines->each(function ($tax) use ($order) {
                $order->tax_lines()->updateOrCreate([
                    'id' => has($tax)->id,
                ], (array) $tax);
            });
        }

        // update order line_items
        if ($resource->filled('line_items')) {
            $order->syncLineItems($resource->input('line_items'));
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
