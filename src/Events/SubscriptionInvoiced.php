<?php

namespace Foundry\Events;

use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionInvoiced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Subscription
     */
    public $subscription;

    /**
     * @var Order
     */
    public $invoice;

    /**
     * Create a new event instance.
     *
     * @param  Subscription  $subscription
     * @param  Order  $invoice
     */
    public function __construct($subscription, $invoice)
    {
        $this->subscription = $subscription;
        $this->invoice = $invoice;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
