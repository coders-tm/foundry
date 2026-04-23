<?php

namespace Workbench\App\Providers;

use Foundry\Foundry;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\Admin;
use Workbench\App\Models\Plan;
use Workbench\App\Models\Subscription;
use Workbench\App\Models\User;
use Workbench\App\Policies\UserPolicy;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        Foundry::useMaskSensitive();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load complete auth configuration from workbench
        $authConfig = require __DIR__.'/../../config/auth.php';
        Config::set('auth', $authConfig);

        // Load complete sanctum configuration from workbench
        $sanctumConfig = require __DIR__.'/../../config/sanctum.php';
        Config::set('sanctum', $sanctumConfig);

        Config::set('cache.default', 'array');
        Config::set('mail.default', 'log');
        Config::set('app.country', 'United States');
        Config::set('app.currency_supported', ['USD', 'INR', 'EUR', 'GBP']);

        // Register workbench app models so Foundry uses them instead of
        // the vendor defaults. Each call also updates the morph map so that
        // polymorphic relationships resolve to the correct class.
        Foundry::useUserModel(User::class);
        Foundry::useAdminModel(Admin::class);
        Foundry::useSubscriptionModel(Subscription::class);
        Foundry::usePlanModel(Plan::class);

        // Register policies
        Gate::policy(User::class, UserPolicy::class);
    }
}
