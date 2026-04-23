<?php

namespace Foundry\Events;

use Foundry\Models\SupportTicket\Reply;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketReplyCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reply;

    public $reply_user;

    public $support_ticket;

    public $support_ticket_user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Reply $reply)
    {
        $this->reply = $reply;
        $this->reply_user = $reply->user;
        $this->support_ticket = $reply->support_ticket;
        $this->support_ticket_user = $this->support_ticket->user;
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
