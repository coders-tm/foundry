<?php

namespace Foundry\Tests;

use Foundry\Foundry;
use Foundry\Models\Order;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class BaseTestCase extends OrchestraTestCase
{
    use WithWorkbench;

    protected function getEnvironmentSetUp($app)
    {
        $apiKey = config('stripe.secret');

        if ($apiKey && ! Str::startsWith($apiKey, 'sk_test_')) {
            throw new InvalidArgumentException('Tests may not be run with a production Stripe key.');
        }

        // Configure admin email for notification tests
        $app['config']->set('foundry.admin_email', 'admin@example.com');

        // Ensure default currency is set for payment tests
        $app['config']->set('app.currency', 'USD');

        Foundry::useOrderModel(Order::class);
        Foundry::useUserModel(\Workbench\App\Models\User::class);
        Foundry::useSubscriptionUserModel(\Workbench\App\Models\User::class);
    }
}
