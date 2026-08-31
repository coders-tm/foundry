<?php

namespace Foundry\Http\Controllers\Webhook;

use Foundry\Events\Paddle\WebhookHandled;
use Foundry\Events\Paddle\WebhookReceived;
use Foundry\Http\Middleware\VerifyPaddleWebhookSignature;
use Foundry\Mandate\Models\Customer as CustomerModel;
use Foundry\Mandate\Models\PaymentMethod as PaymentMethodModel;
use Foundry\Services\PaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
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
     * Handle subscription created event.
     *
     * Stores the Paddle subscription ID on the Customer model and PaymentMethod
     * options when the subscription was created for mandate setup (off-session billing).
     */
    protected function handleSubscriptionCreated(array $payload): Response
    {
        $data = $payload['data'] ?? [];
        $customData = $data['custom_data'] ?? [];

        if (($customData['action'] ?? '') !== 'setup_mandate') {
            return $this->successMethod();
        }

        $userId = $customData['user_id'] ?? null;
        $subscriptionId = $data['id'] ?? null;

        if (! $userId || ! $subscriptionId) {
            return $this->successMethod();
        }

        try {
            $customer = CustomerModel::where('user_id', $userId)
                ->where('provider', PaymentProvider::PADDLE)
                ->first();

            if ($customer) {
                $customer->setSubscriptionId($subscriptionId);
                $customer->save();
            }

            PaymentMethodModel::where('user_id', $userId)
                ->where('provider', PaymentProvider::PADDLE)
                ->whereNull('options->subscription_id')
                ->update([
                    'options->subscription_id' => $subscriptionId,
                ]);
        } catch (\Throwable $e) {
            Log::error('Error storing Paddle subscription ID from webhook', [
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->successMethod();
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
