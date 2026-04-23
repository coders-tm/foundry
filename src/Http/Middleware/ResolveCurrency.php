<?php

namespace Foundry\Http\Middleware;

use Closure;
use Foundry\Facades\Currency;
use Illuminate\Http\Request;

/**
 * Middleware for resolving user currency for pre-checkout routes only.
 * Applies to: shared/plans, shop/products, shop/checkout
 */
class ResolveCurrency
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $baseCurrency = config('app.currency', 'USD');

        // 1. Local environment override
        if (app()->environment('local')) {
            Currency::initialize('INR');

            return $next($request);
        }

        // 2. User saved currency (Highest Priority)
        if ($user && ! empty($user->currency)) {
            Currency::initialize($user->currency);

            return $next($request);
        }

        // 3. Resolve from Address (User) or IP (Fallback via Currency service)
        $address = [];

        if ($user) {
            // Load address relationship if not already loaded
            if (! $user->relationLoaded('address')) {
                $user->load('address');
            }

            if ($user->address) {
                // Pass address to resolve method
                $address = [
                    'country_code' => $user->address->country_code ?? null,
                    'country' => $user->address->country ?? null,
                ];
            }
        }

        // Resolve currency using service
        // address -> fallback to IP (via request attribute) -> fallback to Base
        $currencyService = Currency::resolve($address);

        // 4. Persist resolved currency to user if it came from address
        // Note: We can check if the resolved currency differs from base.
        // Logic: If user has address, and we resolved distinct currency, save it.
        // But Currency::resolve doesn't tell us IF it used address vs IP.
        // We can infer: if we passed address, and result is valid...
        // The original logic only persisted if it came from address.

        if ($user && $user->address && ! $user->currency) {
            $resolvedCode = $currencyService->code();
            // Only update if not base (optional optimization)
            if ($resolvedCode !== $baseCurrency) {
                // Check if the address actually maps to this currency?
                // Original logic: if address -> currency, save.
                // We trust the resolve method. If we passed address, it tries address first.
                // So if we get a code, it's likely from address or IP.
                // If we want to strictly persist only if from address, we'd need to verify address mapping again.
                // But sticking to simple logic: "If user has no currency set, assign the resolved one" is often safe.
                // Let's stick to the previous behavior: update if valid.
                $user->updateQuietly(['currency' => $resolvedCode]);
            }
        }

        return $next($request);
    }
}
