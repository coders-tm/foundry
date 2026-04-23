<?php

namespace Foundry\Events;

use Foundry\Models\SupportTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $support_ticket;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(SupportTicket $support_ticket)
    {
        $this->support_ticket = $support_ticket;
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
