<?php

namespace Foundry\Repository;

use Foundry\Models\Address;
use Foundry\Models\Order;
use Foundry\Models\Order\Contact;
use Foundry\Models\Order\Customer;
use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Order\LineItem;
use Illuminate\Http\Request;

class OrderRepository extends BaseRepository
{
    /**
     * Create repository from request data and calculate order totals
     */
    public static function fromRequest(Request $request, Order $order): Order
    {
        // Process line items
        $line_items = collect($request->line_items ?? [])->map(function ($product) {
            return LineItem::firstOrNew([
                'id' => $product['id'] ?? null,
            ], $product)->fill($product);
        });

        $order->created_at = $order->created_at ?? now();

        $order->fill($request->only([
            'note',
            'collect_tax',
            'attributes',
            'billing_address',
            'source',
        ]));

        // Ensure collect_tax is set to true by default if not provided
        if (! $request->filled('collect_tax')) {
            $order->collect_tax = true;
        }

        $order->setRelation('line_items', $line_items);

        // Process line item discounts
        if ($request->filled('line_items')) {
            foreach ($request->line_items as $key => $product) {
                if (isset($product['discount'])) {
                    $order->line_items[$key]->setRelation('discount', DiscountLine::firstOrNew([
                        'id' => $product['discount']['id'] ?? null,
                    ], $product['discount'])->fill($product['discount']));
                }
            }
        }

        // Set order discount
        $order->setRelation('discount', $request->filled('discount') ? new DiscountLine($request->discount) : null);

        // Process customer data
        if ($request->filled('customer')) {
            $customer = new Customer($request->customer);
            if ($request->filled('customer.address')) {
                $customer->setRelation('address', new Address($request->input('customer.address')));
            }
            $order->setRelation('customer', $customer);
            $order->customer->created_at = $order->customer->created_at ?? now();
            if ($request->filled('customer.id')) {
                $order->customer->id = $request->input('customer.id');
            }
        } else {
            $order->setRelation('customer', null);
        }

        // Set contact
        $order->setRelation('contact', $request->filled('contact') ? new Contact($request->contact) : null);

        // Calculate using CartRepository
        $cartRepository = new self($order->toArray());

        // Apply calculated values to order
        $order->setRelation('tax_lines', $cartRepository->tax_lines);
        $order->fill([
            'sub_total' => $cartRepository->sub_total,
            'tax_total' => $cartRepository->tax_total,
            'discount_total' => $cartRepository->discount_total,
            'grand_total' => $cartRepository->grand_total,
            'line_items_quantity' => $cartRepository->line_items_quantity,
        ]);

        return $order;
    }
}
