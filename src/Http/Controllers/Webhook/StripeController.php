<?php

namespace Foundry\Http\Controllers\Webhook;

use Foundry\Events\Stripe\WebhookHandled;
use Foundry\Events\Stripe\WebhookReceived;
use Foundry\Http\Middleware\VerifyStripeWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Response;

class StripeController extends Controller
{
    /**
     * Create a new WebhookController instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (config('stripe.webhook.secret')) {
            $this->middleware(VerifyStripeWebhookSignature::class);
        }
    }

    /**
     * Handle a Stripe webhook call.
     *
     * @return Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['type']));

        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            $this->setMaxNetworkRetries();

            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

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
        return new Response;
    }

    /**
     * Set the number of automatic retries due to an object lock timeout from Stripe.
     *
     * @param  int  $retries
     * @return void
     */
    protected function setMaxNetworkRetries($retries = 3)
    {
        Stripe::setMaxNetworkRetries($retries);
    }
}
