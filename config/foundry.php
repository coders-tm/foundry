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
    'settings_path' => env('FOUNDRY_SETTINGS_PATH', resource_path('settings.json')),

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

    /*
    |--------------------------------------------------------------------------
    | Mandate / Billable Routing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for callback and redirect routes used during off-session
    | payment method / mandate setups (PayPal, GoCardless, etc.).
    |
    */

    'mandate' => [
        'callback_route' => 'payment-methods.callback',
        'redirect_route' => 'payment-methods.index',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for all payment gateway providers supported by Foundry.
    |
    */

    'payment_providers' => [
        'stripe' => [
            'name' => 'Stripe',
            'label' => 'Credit / Debit Card (Stripe)',
            'provider' => 'stripe',
            'enabled' => (bool) env('STRIPE_ENABLED', true),
            'test_mode' => (bool) env('STRIPE_TEST_MODE', true),
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'supported_currencies' => array_filter(explode(',', env('STRIPE_SUPPORTED_CURRENCIES', ''))),
            'order' => 1,
        ],

        'paypal' => [
            'name' => 'PayPal',
            'label' => 'PayPal',
            'provider' => 'paypal',
            'enabled' => (bool) env('PAYPAL_ENABLED', false),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'webhook_url' => env('PAYPAL_WEBHOOK_URL'),
            'supported_currencies' => array_filter(explode(',', env('PAYPAL_SUPPORTED_CURRENCIES', ''))),
            'order' => 2,
        ],

        'gocardless' => [
            'name' => 'GoCardless',
            'label' => 'Direct Debit (GoCardless)',
            'provider' => 'gocardless',
            'enabled' => (bool) env('GOCARDLESS_ENABLED', false),
            'environment' => env('GOCARDLESS_ENVIRONMENT', 'sandbox'),
            'access_token' => env('GOCARDLESS_ACCESS_TOKEN'),
            'webhook_secret' => env('GOCARDLESS_WEBHOOK_SECRET'),
            'webhook_url' => env('GOCARDLESS_WEBHOOK_URL'),
            'schemes' => [
                'GB' => 'bacs',
                'DE' => 'sepa_core',
                'FR' => 'sepa_core',
                'ES' => 'sepa_core',
                'IT' => 'sepa_core',
                'NL' => 'sepa_core',
                'BE' => 'sepa_core',
                'AU' => 'becs',
                'NZ' => 'becs_nz',
                'US' => 'ach',
                'CA' => 'pad',
                'SE' => 'autogiro',
            ],
            'supported_currencies' => array_filter(explode(',', env('GOCARDLESS_SUPPORTED_CURRENCIES', ''))),
            'order' => 3,
        ],

        'razorpay' => [
            'name' => 'Razorpay',
            'label' => 'Razorpay',
            'provider' => 'razorpay',
            'enabled' => (bool) env('RAZORPAY_ENABLED', false),
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'supported_currencies' => array_filter(explode(',', env('RAZORPAY_SUPPORTED_CURRENCIES', ''))),
            'order' => 4,
        ],

        'klarna' => [
            'name' => 'Klarna',
            'label' => 'Klarna',
            'provider' => 'klarna',
            'enabled' => (bool) env('KLARNA_ENABLED', false),
            'api_key' => env('KLARNA_API_KEY'),
            'api_secret' => env('KLARNA_API_SECRET'),
            'webhook_url' => env('KLARNA_WEBHOOK_URL'),
            'test_mode' => (bool) env('KLARNA_TEST_MODE', true),
            'supported_currencies' => array_filter(explode(',', env('KLARNA_SUPPORTED_CURRENCIES', ''))),
            'order' => 5,
        ],

        'mercadopago' => [
            'name' => 'MercadoPago',
            'label' => 'MercadoPago',
            'provider' => 'mercadopago',
            'enabled' => (bool) env('MERCADOPAGO_ENABLED', false),
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
            'webhook_url' => env('MERCADOPAGO_WEBHOOK_URL'),
            'test_mode' => (bool) env('MERCADOPAGO_TEST_MODE', true),
            'supported_currencies' => array_filter(explode(',', env('MERCADOPAGO_SUPPORTED_CURRENCIES', ''))),
            'order' => 6,
        ],

        'paystack' => [
            'name' => 'Paystack',
            'label' => 'Paystack',
            'provider' => 'paystack',
            'enabled' => (bool) env('PAYSTACK_ENABLED', false),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'webhook_url' => env('PAYSTACK_WEBHOOK_URL'),
            'test_mode' => (bool) env('PAYSTACK_TEST_MODE', true),
            'supported_currencies' => array_filter(explode(',', env('PAYSTACK_SUPPORTED_CURRENCIES', ''))),
            'order' => 7,
        ],

        'xendit' => [
            'name' => 'Xendit',
            'label' => 'Xendit',
            'provider' => 'xendit',
            'enabled' => (bool) env('XENDIT_ENABLED', false),
            'public_key' => env('XENDIT_PUBLIC_KEY'),
            'secret_key' => env('XENDIT_SECRET_KEY'),
            'webhook_url' => env('XENDIT_WEBHOOK_URL'),
            'test_mode' => (bool) env('XENDIT_TEST_MODE', true),
            'supported_currencies' => array_filter(explode(',', env('XENDIT_SUPPORTED_CURRENCIES', ''))),
            'order' => 8,
        ],

        'flutterwave' => [
            'name' => 'Flutterwave',
            'label' => 'Flutterwave',
            'provider' => 'flutterwave',
            'enabled' => (bool) env('FLUTTERWAVE_ENABLED', false),
            'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
            'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
            'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
            'environment' => env('FLUTTERWAVE_TEST_MODE', true) ? 'sandbox' : 'live',
            'webhook_url' => env('FLUTTERWAVE_WEBHOOK_URL'),
            'test_mode' => (bool) env('FLUTTERWAVE_TEST_MODE', true),
            'supported_currencies' => array_filter(explode(',', env('FLUTTERWAVE_SUPPORTED_CURRENCIES', ''))),
            'order' => 9,
        ],

        'alipay' => [
            'name' => 'Alipay',
            'label' => 'Alipay',
            'provider' => 'alipay',
            'enabled' => (bool) env('ALIPAY_ENABLED', false),
            'app_id' => env('ALIPAY_APP_ID'),
            'public_key' => env('ALIPAY_ALI_PUBLIC_KEY'),
            'private_key' => env('ALIPAY_PRIVATE_KEY'),
            'mode' => env('ALIPAY_TEST_MODE', true) ? 'sandbox' : 'normal',
            'webhook_url' => env('ALIPAY_WEBHOOK_URL'),
            'supported_currencies' => array_filter(explode(',', env('ALIPAY_SUPPORTED_CURRENCIES', ''))),
            'order' => 10,
        ],

        'paddle' => [
            'name' => 'Paddle',
            'label' => 'Paddle',
            'provider' => 'paddle',
            'enabled' => (bool) env('PADDLE_ENABLED', false),
            'api_key' => env('PADDLE_API_KEY'),
            'client_token' => env('PADDLE_CLIENT_TOKEN'),
            'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
            'environment' => env('PADDLE_TEST_MODE', true) ? 'sandbox' : 'live',
            'test_mode' => (bool) env('PADDLE_TEST_MODE', true),
            'webhook_url' => env('PADDLE_WEBHOOK_URL'),
            'supported_currencies' => array_filter(explode(',', env('PADDLE_SUPPORTED_CURRENCIES', ''))),
            'order' => 11,
        ],

        'wallet' => [
            'name' => 'Wallet',
            'label' => 'Account Wallet',
            'provider' => 'wallet',
            'enabled' => (bool) env('WALLET_ENABLED', true),
            'order' => 12,
        ],

        'manual' => [
            'name' => 'Manual Payment',
            'label' => 'Bank Transfer / Manual',
            'provider' => 'manual',
            'enabled' => (bool) env('MANUAL_PAYMENT_ENABLED', true),
            'order' => 13,
        ],
    ],

];
