<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */
    'domain' => env('APP_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    'api_prefix' => env('APP_API_PREFIX', 'api'),
    'admin_prefix' => env('APP_ADMIN_PREFIX', 'admin'),
    'tunnel_domain' => env('TUNNEL_WEB_DOMAIN', null),
    'admin_email' => env('APP_ADMIN_EMAIL', null),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'app_url' => env('APP_URL', 'http://localhost'),
    'admin_url' => env('APP_ADMIN_URL', 'http://localhost/admin'),

    /*
    |--------------------------------------------------------------------------
    | Settings to Config Override Mapping
    |--------------------------------------------------------------------------
    |
    | This configuration defines how app settings from the database override
    | Laravel's configuration values. When settings are loaded, the system will
    | automatically update the corresponding config values based on this mapping.
    |
    */

    'settings_override' => [
        'config' => [
            'alias' => 'app',
            'subscription' => 'foundry.subscription',
            'checkout' => 'foundry.shop',
            'email' => [
                'foundry.admin_email',
                'mail.from.address',
            ],
            'name' => ['mail.from.name'],
            'currency' => 'stripe.currency',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    |
    | Base currency is the system currency used for storage and calculations.
    | Display currency is determined per-request by middleware.
    |
    */

    'currency' => [
        // Supported currencies list (empty array means allow all)
        'supported' => array_filter(explode(',', env('APP_SUPPORTED_CURRENCIES', ''))),

        // Enable currency auto-detection by user address/IP
        'auto_detect' => (bool) env('CURRENCY_AUTO_DETECT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | License Security Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the license verification and security system.
    | DO NOT modify these unless you understand the security implications.
    |
    */

    'license_key' => env('APP_LICENSE_KEY'),
    'app_id' => env('APP_ID', null),
    'product_id' => env('PRODUCT_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Subscription
    |--------------------------------------------------------------------------
    |
    | Controls subscription-specific behaviors.
    |
    */

    'subscription' => [
        // When true, activating a late payer anchors from the open invoice's intended
        // start date (last unpaid period start) + plan duration; otherwise, uses today.
        'anchor_from_invoice' => (bool) env('SUBSCRIPTION_ANCHOR_FROM_INVOICE', true),

        // Grace period in days for overdue payments before subscription expires
        'grace_period_days' => (int) env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 0),

        // Freeze configuration
        'freeze_fee' => (float) env('SUBSCRIPTION_FREEZE_FEE', 0.00), // Fee charged per freeze period
        'allow_freeze' => (bool) env('SUBSCRIPTION_ALLOW_FREEZE', true), // Enable/disable freeze functionality

        // Setup fee configuration
        'setup_fee' => (float) env('SUBSCRIPTION_SETUP_FEE', 0.00), // One-time admission fee
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Guard Context Configuration
    |--------------------------------------------------------------------------
    |
    | This defines the context-aware paths and settings for different guards.
    | The system uses these patterns to resolve the active guard context.
    |
    */

    'guards' => [
        'admin' => [
            'paths' => [
                env('APP_ADMIN_PREFIX', 'admin'),
                env('APP_ADMIN_PREFIX', 'admin').'/*',
            ],
            'guard' => 'admin',
            'password_broker' => 'admin',
            'home' => '/admin',
            'login_route' => 'admin.login',
            'two_factor_route' => 'admin.two-factor.login',
            'password_reset_route' => 'admin.password.reset',
        ],
        'user' => [
            'paths' => ['*'],
            'guard' => 'user',
            'password_broker' => 'user',
            'home' => '/dashboard',
            'login_route' => 'login',
            'two_factor_route' => 'two-factor.login',
            'password_reset_route' => 'password.reset',
        ],
    ],

    'wallet' => [
        // Enable wallet functionality
        'enabled' => (bool) env('WALLET_ENABLED', true),

        // Automatically charge from wallet during subscription renewal if balance is available
        'auto_charge_on_renewal' => (bool) env('WALLET_AUTO_CHARGE_ON_RENEWAL', true),
    ],

];
