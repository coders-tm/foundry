<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe Keys
    |--------------------------------------------------------------------------
    |
    | The Stripe publishable key and secret key give you access to Stripe's
    | API. The "publishable" key is typically used when interacting with
    | Stripe.js while the "secret" key accesses private API endpoints.
    |
    */

    'id' => env('STRIPE_ID', null),
    'key' => env('STRIPE_KEY', null),
    'secret' => env('STRIPE_SECRET', null),

    /*
    |--------------------------------------------------------------------------
    | Stripe Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. Of course, you are welcome to use any of the
    | various world currencies that are currently supported via Stripe.
    |
    */

    'currency' => env('STRIPE_CURRENCY', 'usd'),

    'currency_locale' => env('STRIPE_CURRENCY_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhooks
    |--------------------------------------------------------------------------
    |
    | Your Stripe webhook secret is used to prevent unauthorized requests to
    | your webhook handling controllers. The tolerance allows you to specify
    | the maximum number of seconds of difference between webhook timestamps.
    |
    */

    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET', null),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of currencies supported by this Stripe account.
    | Leave empty to allow all currencies.
    |
    */

    'supported_currencies' => array_filter(explode(',', env('STRIPE_SUPPORTED_CURRENCIES', ''))),

];
