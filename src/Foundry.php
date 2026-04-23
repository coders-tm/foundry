<?php

namespace Foundry;

use DateTimeInterface;
use Flutterwave\Config\PackageConfig;
use Flutterwave\Flutterwave;
use Foundry\Services\Payment\KlarnaClient;
use Foundry\Services\Payment\MercadoPagoClient;
use Foundry\Services\Payment\XenditClient;
use GoCardlessPro\Client;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;
use Razorpay\Api\Api;
use Srmklive\PayPal\Services\PayPal;
use Stripe\StripeClient;
use Stripe\Util\ApiVersion as StripeApiVersion;
use Yabacon\Paystack;
use Yansongda\Pay\Gateways\Alipay;
use Yansongda\Pay\Pay;

class Foundry
{
    /**
     * The format used for serializing DateTime instances.
     * This format is applied when converting DateTime objects to strings,
     * particularly during array/JSON serialization.
     *
     * @var string
     */
    public static $dateTimeFormat = DateTimeInterface::ATOM;

    /**
     * The user model class name.
     *
     * @var string
     */
    public static $userModel = 'App\\Models\\User';

    /**
     * The subscription user model class name.
     *
     * @var string
     */
    public static $subscriptionUserModel = 'App\\Models\\User';

    /**
     * The default admin model class name.
     *
     * @var string
     */
    public static $adminModel = 'App\\Models\\Admin';

    /**
     * The default support ticket model class name.
     *
     * @var string
     */
    public static $supportTicketModel = 'Foundry\\Models\\SupportTicket';

    /**
     * The default subscription model class name.
     *
     * @var string
     */
    public static $subscriptionModel = 'Foundry\\Models\\Subscription';

    /**
     * The default order model class name (subscription orders).
     *
     * @var string
     */
    public static $orderModel = 'Foundry\\Models\\Order';

    /**
     * The default plan model class name.
     *
     * @var string
     */
    public static $planModel = 'Foundry\\Models\\Subscription\\Plan';

    /**
     * The default coupon model class name.
     *
     * @var string
     */
    public static $couponModel = 'Foundry\\Models\\Coupon';

    /**
     * The auto-renewal customer model class name.
     *
     * @var string
     */
    public static $autoRenewalCustomerModel = 'Foundry\\AutoRenewal\\Models\\Customer';

    /**
     * The auto-renewal payment method model class name.
     *
     * @var string
     */
    public static $autoRenewalPaymentMethodModel = 'Foundry\\AutoRenewal\\Models\\PaymentMethod';

    /**
     * Indicates if Foundry's migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * Indicates if Foundry's routes will be register.
     *
     * @var bool
     */
    public static $registersRoutes = true;

    /**
     * Indicates if MaskSensitiveConfig should be used as the global Blade compiler.
     *
     * @var bool
     */
    public static $usesMaskSensitive = false;

    /**
     *  app short codes.
     *
     * @var bool
     */
    public static $appShortCodes = [];

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * The cached GoCardless client instance.
     *
     * @var Client
     */
    protected static $gocardlessClient;

    /**
     * The cached PayPal client instance.
     *
     * @var PayPal
     */
    protected static $paypalClient;

    /**
     * The cached Razorpay client instance.
     *
     * @var Api
     */
    protected static $razorpayClient;

    /**
     * Determine if Foundry's migrations should be run.
     *
     * @return bool
     */
    public static function shouldRunMigrations()
    {
        return static::$runsMigrations;
    }

    /**
     * Determine if Foundry's routes will be register.
     *
     * @return bool
     */
    public static function shouldRegistersRoutes()
    {
        return static::$registersRoutes;
    }

    /**
     * Configure Foundry to not register it's routes.
     */
    public static function ignoreRoutes(): bool
    {
        static::$registersRoutes = false;

        return true;
    }

    /**
     * Configure Foundry to use MaskSensitiveConfig as the global Blade compiler.
     *
     * @return static
     */
    public static function useMaskSensitive()
    {
        static::$usesMaskSensitive = true;

        return new self;
    }

    /**
     * Determine if MaskSensitiveConfig should be used as the global Blade compiler.
     *
     * @return bool
     */
    public static function shouldUseMaskSensitive()
    {
        return static::$usesMaskSensitive;
    }

    /**
     * Configure Foundry to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new self;
    }

    /**
     * Set the user model class name.
     *
     * @param  string  $userModel
     * @return void
     */
    public static function useUserModel($userModel)
    {
        static::$userModel = $userModel;

        static::useSubscriptionUserModel($userModel);

        Config::set('auth.providers.users.model', $userModel);

        // Register morph map for user model
        Relation::morphMap([
            'User' => $userModel,
        ]);
    }

    /**
     * Set the subscription user model class name.
     *
     * @param  string  $subscriptionUserModel
     * @return void
     */
    public static function useSubscriptionUserModel($subscriptionUserModel)
    {
        static::$subscriptionUserModel = $subscriptionUserModel;
    }

    /**
     * Set the admin model class name.
     *
     * @param  string  $adminModel
     * @return void
     */
    public static function useAdminModel($adminModel)
    {
        static::$adminModel = $adminModel;

        Config::set('auth.providers.admins.model', $adminModel);

        // Register morph map for admin model
        Relation::morphMap([
            'Admin' => $adminModel,
        ]);
    }

    /**
     * Set the support ticket model class name.
     *
     * @param  string  $supportTicketModel
     * @return void
     */
    public static function useSupportTicketModel($supportTicketModel)
    {
        static::$supportTicketModel = $supportTicketModel;

        // Register morph map for support ticket model
        Relation::morphMap([
            'SupportTicket' => $supportTicketModel,
        ]);
    }

    /**
     * Set the order model class name.
     *
     * @param  string  $orderModel
     * @return void
     */
    public static function useOrderModel($orderModel)
    {
        static::$orderModel = $orderModel;

        // Register morph map for order model
        Relation::morphMap([
            'Order' => $orderModel,
        ]);
    }

    /**
     * Set the subscription model class name.
     *
     * @param  string  $subscriptionModel
     * @return void
     */
    public static function useSubscriptionModel($subscriptionModel)
    {
        static::$subscriptionModel = $subscriptionModel;

        // Register morph map for subscription model
        Relation::morphMap([
            'Subscription' => $subscriptionModel,
        ]);
    }

    /**
     * Set the plan model class name.
     *
     * @param  string  $planModel
     * @return void
     */
    public static function usePlanModel($planModel)
    {
        static::$planModel = $planModel;

        // Register morph map for plan model
        Relation::morphMap([
            'Plan' => $planModel,
        ]);
    }

    /**
     * Set the coupon model class name.
     *
     * @param  string  $couponModel
     * @return void
     */
    public static function useCouponModel($couponModel)
    {
        static::$couponModel = $couponModel;

        // Register morph map for coupon model
        Relation::morphMap([
            'Coupon' => $couponModel,
        ]);
    }

    /**
     * Set the auto-renewal customer model class name.
     *
     * @param  string  $model
     * @return void
     */
    public static function useAutoRenewalCustomerModel($model)
    {
        static::$autoRenewalCustomerModel = $model;
    }

    /**
     * Set the auto-renewal payment method model class name.
     *
     * @param  string  $model
     * @return void
     */
    public static function useAutoRenewalPaymentMethodModel($model)
    {
        static::$autoRenewalPaymentMethodModel = $model;
    }

    /**
     * Set app short codes.
     *
     * @return void
     */
    public static function useAppShortCodes(array $appShortCodes)
    {
        static::$appShortCodes = $appShortCodes;
    }

    /**
     * Get the GoCardless client instance.
     *
     * @return Client
     */
    public static function gocardless(array $options = [])
    {
        if (static::$gocardlessClient) {
            return static::$gocardlessClient;
        }

        $environment = $options['environment'] ?? config('gocardless.environment', 'sandbox');
        $accessToken = $options['access_token'] ?? config('gocardless.access_token');

        $clientOptions = array_merge([
            'environment' => $environment,
            'access_token' => $accessToken,
        ], $options);

        return static::$gocardlessClient = new Client($clientOptions);
    }

    /**
     * Get the paypal client instance.
     *
     * @return PayPal
     */
    public static function paypal(array $options = [])
    {
        if (static::$paypalClient) {
            return static::$paypalClient;
        }

        $options = array_merge(config('paypal'), $options);

        $provider = new PayPal;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        return static::$paypalClient = $provider;
    }

    /**
     * Get the razorpay client instance.
     *
     * @return Api
     */
    public static function razorpay(array $options = [])
    {
        if (static::$razorpayClient) {
            return static::$razorpayClient;
        }

        $keyId = $options['key_id'] ?? config('razorpay.key_id');
        $keySecret = $options['key_secret'] ?? config('razorpay.key_secret');

        return static::$razorpayClient = new Api($keyId, $keySecret);
    }

    /**
     * The cached Stripe client instance.
     *
     * @var StripeClient|null
     */
    protected static $stripeClient;

    /**
     * Get the Stripe client instance.
     *
     * @return StripeClient
     */
    public static function stripe(array $options = [])
    {
        if (static::$stripeClient) {
            return static::$stripeClient;
        }

        $config = array_merge([
            'api_key' => $options['api_key'] ?? config('stripe.secret'),
            'stripe_version' => StripeApiVersion::CURRENT,
        ], $options);

        return static::$stripeClient = app(StripeClient::class, ['config' => $config]);
    }

    /**
     * Format the given amount into a displayable currency.
     */
    public static function formatAmount(int $amount, ?string $currency = null, ?string $locale = null, array $options = []): string
    {
        $money = new Money($amount, new Currency(strtoupper($currency ?? config('stripe.currency', 'USD'))));

        $locale = $locale ?? config('stripe.currency_locale', config('app.locale', 'en'));

        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        if (isset($options['min_fraction_digits'])) {
            $numberFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $options['min_fraction_digits']);
        }

        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies);

        return $moneyFormatter->format($money);
    }

    /**
     * The cached Klarna client instance.
     *
     * @var KlarnaClient|null
     */
    protected static $klarnaClient;

    /**
     * Get the Klarna client instance (custom Guzzle-based client).
     *
     * @return KlarnaClient|null
     */
    public static function klarna(array $options = [])
    {
        if (static::$klarnaClient) {
            return static::$klarnaClient;
        }

        return static::$klarnaClient = new KlarnaClient($options);
    }

    /**
     * The cached MercadoPago client instance.
     *
     * @var MercadoPagoClient|null
     */
    protected static $mercadopagoClient;

    /**
     * Get the MercadoPago client instance (custom client).
     *
     * @return MercadoPagoClient|null
     */
    public static function mercadopago(array $options = [])
    {
        if (static::$mercadopagoClient) {
            return static::$mercadopagoClient;
        }

        return static::$mercadopagoClient = new MercadoPagoClient($options);
    }

    /**
     * The cached Paystack client instance.
     *
     * @var Paystack|null
     */
    protected static $paystackClient;

    /**
     * Get the Paystack client instance.
     *
     * @return Paystack|null
     */
    public static function paystack(array $options = [])
    {
        if (static::$paystackClient) {
            return static::$paystackClient;
        }
        $secretKey = $options['secret_key'] ?? config('paystack.secret_key');
        if ($secretKey) {
            return static::$paystackClient = new Paystack($secretKey);
        }

        return null;
    }

    /**
     * The cached Xendit client instance.
     *
     * @var XenditClient|null
     */
    protected static $xenditClient;

    /**
     * Get the Xendit client instance (custom client).
     *
     * @return XenditClient|null
     */
    public static function xendit(array $options = [])
    {
        if (static::$xenditClient) {
            return static::$xenditClient;
        }

        return static::$xenditClient = new XenditClient($options);
    }

    /**
     * The cached Flutterwave client instance.
     *
     * @var Flutterwave|null
     */
    protected static $flutterwaveClient;

    /**
     * Get the Flutterwave client instance (official SDK v3).
     * This method initializes the Flutterwave SDK with credentials.
     *
     * @return Flutterwave|null
     */
    public static function flutterwave(array $options = [])
    {
        if (static::$flutterwaveClient !== null) {
            return static::$flutterwaveClient;
        }

        $publicKey = $options['public_key'] ?? config('flutterwave.public_key');
        $secretKey = $options['secret_key'] ?? config('flutterwave.secret_key');
        $encryptionKey = $options['encryption_key'] ?? config('flutterwave.encryption_key');
        $environment = $options['environment'] ?? config('flutterwave.environment', 'sandbox');

        if ($secretKey) {
            // Set environment variables that the SDK expects
            if (! defined('FLW_SECRET_KEY')) {
                define('FLW_SECRET_KEY', $secretKey);
            }
            if ($publicKey && ! defined('FLW_PUBLIC_KEY')) {
                define('FLW_PUBLIC_KEY', $publicKey);
            }
            if ($encryptionKey && ! defined('FLW_ENCRYPTION_KEY')) {
                define('FLW_ENCRYPTION_KEY', $encryptionKey);
            }
            if (! defined('FLW_ENV')) {
                define('FLW_ENV', $environment);
            }

            // Create and configure the Flutterwave client
            $config = PackageConfig::setUp(
                $secretKey,
                $publicKey,
                $encryptionKey,
                $environment
            );

            // Set Laravel logs path for Flutterwave SDK
            if (! defined('FLW_LOGS_PATH')) {
                define('FLW_LOGS_PATH', storage_path('logs'));
            }

            Flutterwave::bootstrap($config);

            return static::$flutterwaveClient = new Flutterwave;
        }

        return static::$flutterwaveClient = null;
    }

    /**
     * The cached Apple Pay client instance (via Stripe).
     *
     * @var StripeClient|null
     */
    protected static $applePayClient;

    /**
     * Apple Pay is integrated via Stripe. Use the cached stripe() client for Apple Pay operations.
     */
    public static function applePay(array $options = [])
    {
        if (static::$applePayClient) {
            return static::$applePayClient;
        }

        return static::$applePayClient = static::stripe($options);
    }

    /**
     * The cached Google Pay client instance (via Stripe).
     *
     * @var StripeClient|null
     */
    protected static $googlePayClient;

    /**
     * Google Pay is integrated via Stripe. Use the cached stripe() client for Google Pay operations.
     */
    public static function googlePay(array $options = [])
    {
        if (static::$googlePayClient) {
            return static::$googlePayClient;
        }

        return static::$googlePayClient = static::stripe($options);
    }

    /**
     * The cached Alipay client instance.
     *
     * @var Alipay|null
     */
    protected static $alipayClient;

    /**
     * Get the Alipay client instance.
     */
    public static function alipay(array $options = [])
    {
        if (static::$alipayClient) {
            return static::$alipayClient;
        }

        $config = config('alipay');

        if ($config && ! empty($config['app_id'])) {
            Pay::config([
                'alipay' => [
                    'default' => [
                        'app_id' => $config['app_id'],
                        'ali_public_key' => $config['ali_public_key'],
                        'private_key' => $config['private_key'],
                        'notify_url' => $config['webhook_url'],
                        'mode' => $config['mode'] === 'sandbox' ? Pay::MODE_SANDBOX : Pay::MODE_NORMAL,
                    ],
                ],
                'logger' => [
                    'enable' => true,
                    'file' => storage_path('logs/alipay.log'),
                    'level' => 'debug',
                    'type' => 'daily',
                    'max_file' => 30,
                ],
            ]);

            return static::$alipayClient = Pay::alipay();
        }

        return static::$alipayClient = null;
    }
}
