<?php

namespace Foundry\Events\GoCardless;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlowCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * The completed redirect flow.
     *
     * @var object
     */
    public $flow;

    /**
     * The subscription associated with the flow.
     *
     * @var object
     */
    public $subscription;

    /**
     * The mandate ID associated with the flow.
     *
     * @var string
     */
    public $mandateId;

    /**
     * The customer ID associated with the flow.
     *
     * @var string
     */
    public $customerId;

    /**
     * Create a new event instance.
     *
     * @param  object  $subscription
     * @param  object  $flow
     * @return void
     */
    public function __construct($subscription, $flow)
    {
        $this->flow = $flow;
        $this->mandateId = $flow->links->mandate;
        $this->customerId = $flow->links->customer;
        $this->subscription = $subscription;
    }
}
