<?php

namespace Foundry\Http\Middleware;

use Closure;
use Foundry\Foundry;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyPaddleWebhookSignature
{
    /**
     * Handle an incoming webhook request and verify signature using Paddle SDK / Secret.
     *
     * @return mixed
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next)
    {
        $secret = config('foundry.payment_providers.paddle.webhook_secret');
        $signature = $request->header('Paddle-Signature');

        if ($secret && $signature) {
            try {
                $paddle = Foundry::paddle();
                $rawBody = $request->getContent();
                $paddle->webhooks->unmarshal($rawBody, $secret, $signature);
            } catch (\Throwable $e) {
                throw new AccessDeniedHttpException('Invalid Paddle webhook signature: '.$e->getMessage(), $e);
            }
        }

        return $next($request);
    }
}
