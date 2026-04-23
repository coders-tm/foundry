<?php

namespace Foundry\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\WebhookSignature;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyStripeWebhookSignature
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
     *
     * @throws AccessDeniedHttpException
     */
    public function handle($request, Closure $next)
    {
        try {
            WebhookSignature::verifyHeader(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('stripe.webhook.secret'),
                config('stripe.webhook.tolerance')
            );
        } catch (SignatureVerificationException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return $next($request);
    }
}
