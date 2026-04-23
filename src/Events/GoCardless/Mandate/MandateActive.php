<?php

namespace Foundry\Events\GoCardless\Mandate;

use Foundry\Events\GoCardless\GoCardlessEvent;

class MandateActive extends GoCardlessEvent
{
    /**
     * The ID of the mandate.
     *
     * @var string
     */
    public $mandateId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->mandateId = $payload['links']['mandate'] ?? null;
    }
}
