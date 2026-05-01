<?php

namespace Foundry\Events;

use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var Subscription|null */
    public $subscription;

    /** @var Order|null */
    public $order;

    /**
     * @param  Subscription|Subscription  $subscription
     * @param  Order|null  $order
     */
    public function __construct($subscription, $order = null)
    {
        $this->subscription = $subscription;
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
