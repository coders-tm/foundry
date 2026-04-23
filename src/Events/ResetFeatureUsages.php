<?php

namespace Foundry\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResetFeatureUsages
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $features;

    public $subscription;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(mixed $subscription, mixed $features)
    {
        $this->subscription = $subscription;
        $this->features = $features;
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
