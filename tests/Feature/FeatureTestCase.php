<?php

namespace Foundry\Tests\Feature;

use Foundry\Foundry;
use Foundry\Http\Controllers\PaymentController;
use Foundry\Tests\TestCase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Hash;
use Stripe\ApiRequestor as StripeApiRequestor;
use Stripe\HttpClient\CurlClient as StripeCurlClient;
use Stripe\StripeClient;
use Workbench\App\Models\User;

abstract class FeatureTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (! getenv('STRIPE_SECRET')) {
            $this->markTestSkipped('Stripe secret key not set.');
        }

        parent::setUp();

        config(['app.url' => 'http://localhost']);

        $curl = new StripeCurlClient([CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]);
        $curl->setEnableHttp2(false);
        StripeApiRequestor::setHttpClient($curl);
    }

    protected static function stripe(array $options = []): StripeClient
    {
        return Foundry::stripe(array_merge(['api_key' => getenv('STRIPE_SECRET')], $options));
    }

    protected function createCustomer($description = 'dipak', array $options = []): User
    {
        return User::create(array_merge([
            'email' => "{$description}@foundry.com",
            'first_name' => 'Dipak',
            'last_name' => 'Sarkar',
            'password' => Hash::make('password'),
        ], $options));
    }

    /**
     * Define the routes for the application.
     *
     * @param  Router  $router
     * @return void
     */
    protected function defineRoutes($router)
    {
        $router->group(['prefix' => 'payment', 'as' => 'payment.'], function () use ($router) {
            $router->get('status/{token}', [PaymentController::class, 'status'])->name('status');
            $router->post('{provider}/setup-intent', [PaymentController::class, 'setupPaymentIntent'])->name('setup-intent');
            $router->post('{provider}/confirm', [PaymentController::class, 'confirmPayment'])->name('confirm');
        });
    }
}
