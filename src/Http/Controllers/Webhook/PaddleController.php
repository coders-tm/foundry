<?php

namespace Foundry\Http\Controllers\Webhook;

use Foundry\Events\Paddle\WebhookHandled;
use Foundry\Events\Paddle\WebhookReceived;
use Foundry\Http\Middleware\VerifyPaddleWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PaddleController extends Controller
{
    /**
     * Create a new PaddleController instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (config('foundry.payment_providers.paddle.webhook_secret')) {
            $this->middleware(VerifyPaddleWebhookSignature::class);
        }
    }

    /**
     * Handle a Paddle webhook call.
     *
     * @param  Request  $request
     * @return Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || empty($payload['event_type'])) {
            return new Response('Invalid Payload', 400);
        }

        $eventType = $payload['event_type'];
        $method = 'handle'.Str::studly(str_replace('.', '_', $eventType));

        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

        WebhookHandled::dispatch($payload);

        return $this->missingMethod($payload);
    }

    /**
     * Handle successful calls on the controller.
     *
     * @param  array  $parameters
     * @return Response
     */
    protected function successMethod($parameters = [])
    {
        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $parameters
     * @return Response
     */
    protected function missingMethod($parameters = [])
    {
        return new Response('Event Received Unhandled', 200);
    }
}
