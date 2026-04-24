<?php

namespace Foundry\Providers;

use Foundry\Commands;
use Foundry\Contracts\ConfigurationInterface;
use Foundry\Contracts\StateInterface;
use Foundry\Facades\Guard;
use Foundry\Foundry;
use Foundry\Http\Middleware;
use Foundry\Models;
use Foundry\Notifications;
use Foundry\Services\AdminNotification;
use Foundry\Services\ApplicationState;
use Foundry\Services\BlogService;
use Foundry\Services\ConfigLoader;
use Foundry\Services\Currency;
use Foundry\Services\GuardManager;
use Foundry\Services\MaskSensitiveConfig;
use Foundry\Services\ResponseOptimizer;
use Foundry\Services\StateLoader;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\PendingResourceRegistration;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class FoundryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->configure();

        $this->app->singleton(GuardManager::class);

        // Register Currency as request-scoped service
        $this->app->scoped('currency', function () {
            return new Currency;
        });

        $this->app->singleton(AdminNotification::class);

        $this->app->singleton(
            ConfigurationInterface::class,
            ConfigLoader::class
        );

        $this->app->singleton(
            StateInterface::class,
            StateLoader::class
        );

        $this->app->alias(
            ConfigurationInterface::class,
            'core.config'
        );

        // Register Blog service and facade
        $this->app->singleton('blog', function ($app) {
            return new BlogService;
        });

        // Register MaskSensitiveConfig as a singleton for direct usage (e.g. ThemeController)
        $this->app->singleton(MaskSensitiveConfig::class, function ($app) {
            return new MaskSensitiveConfig(
                $app['files'],
                $app['config']['view.compiled'],
                $app['config']->get('view.relative_hash', false) ? $app->basePath() : '',
                $app['config']->get('view.cache', true),
                $app['config']->get('view.compiled_extension', 'php'),
            );
        });

        // Swap the global Blade compiler when useMaskSensitive() is enabled
        $this->app->extend('blade.compiler', function ($compiler, $app) {
            if (! Foundry::shouldUseMaskSensitive()) {
                return $compiler;
            }

            return $app->make(MaskSensitiveConfig::class);
        });

        // Register the ViewComposerServiceProvider
        $this->app->register(ViewComposerServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Critical: Initialize application core
        $this->bootstrapApplicationCore();

        $this->registerRouteMiddleware();
        $this->registerResources();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerRoutes();
        $this->defineManagementRoutes();

        App::setLocale(app_lang());

        $this->registerMorphMap();

        $this->loadConfigFromDatabase();

        Paginator::useBootstrapFive();

        PendingResourceRegistration::macro('withLogs', function () {
            /** @var PendingResourceRegistration $this */
            /** @phpstan-ignore-next-line */
            $name = $this->name;
            /** @phpstan-ignore-next-line */
            $controller = $this->controller;

            $singular = str_replace('-', '_', Str::singular(last(explode('.', $name))));

            Route::get("{$name}/{{$singular}}/logs", [$controller, 'logs'])->name("{$name}.logs");
            Route::post("{$name}/{{$singular}}/logs", [$controller, 'storeLog'])->name("{$name}.store-log");

            return $this;
        });

        PendingResourceRegistration::macro('withExport', function () {
            /** @var PendingResourceRegistration $this */
            /** @phpstan-ignore-next-line */
            $name = $this->name;
            /** @phpstan-ignore-next-line */
            $controller = $this->controller;

            Route::get("{$name}/export", [$controller, 'export'])->name("{$name}.export");

            return $this;
        });

        // Register core middleware
        $this->registerCoreMiddleware();

        // Register Request macro for IP Location
        Request::macro('ipLocation', function ($key = null, $default = null) {
            /** @var Request $this */
            $location = $this->attributes->get('ip_location');

            if (! $location) {
                return $default;
            }

            if (is_null($key)) {
                return $location;
            }

            return data_get($location, $key, $default);
        });

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $baseUrl = app_url(config('foundry.reset_password_url'));

            if ($user->guard === 'admins') {
                $baseUrl = admin_url(config('foundry.reset_password_url'));
            }

            return $baseUrl."?token={$token}&email={$user->email}";
        });

        ResetPassword::toMailUsing(function ($user, string $token) {
            $notification = new Notifications\UserResetPasswordNotification($user, [
                'url' => call_user_func(ResetPassword::$createUrlCallback, $user, $token) ?? null,
                'token' => $token,
                'expires' => now()->addMinutes(config('auth.passwords.'.($user->guard ?? 'users').'.expire', 60))->format('Y-m-d H:i:s'),
            ]);

            return $notification->toMail($user);
        });

        Authenticate::redirectUsing(function ($request) {
            return route(Guard::loginRoute($request));
        });

        RedirectIfAuthenticated::redirectUsing(function ($request) {
            return Guard::home($request);
        });
    }

    /**
     * Setup the configuration for Foundry.
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(
            $this->packagePath('config/foundry.php'),
            'foundry'
        );

        $this->mergeConfigFrom(
            $this->packagePath('config/stripe.php'),
            'stripe'
        );
    }

    /**
     * Register the package migrations.
     *
     * @return void
     */
    protected function registerMigrations()
    {
        if (Foundry::shouldRunMigrations()) {
            $this->loadMigrationsFrom($this->packagePath('database/migrations'));
        }
    }

    /**
     * Load config from databse.
     *
     * @return void
     */
    protected function loadConfigFromDatabase()
    {
        try {
            // Load app config
            Models\Setting::syncConfig();

            // Load payment methods config
            Models\PaymentMethod::syncConfig();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Register the morph map for the package.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            'User' => Foundry::$userModel,
            'Admin' => Foundry::$adminModel,
            'Order' => Foundry::$orderModel,
            'Plan' => Foundry::$planModel,
            'Subscription' => Foundry::$subscriptionModel,
            'Coupon' => Foundry::$couponModel,
            'SupportTicket' => Foundry::$supportTicketModel,
            'Address' => Models\Address::class,
            'Group' => Models\Group::class,
            'Permission' => Models\Permission::class,
            'LineItem' => Models\Order\LineItem::class,
        ]);
    }

    /**
     * Register the package resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom($this->packagePath('resources/views'), 'foundry');
        $this->loadJsonTranslationsFrom($this->packagePath('resources/lang'));
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->packagePath('config/foundry.php') => $this->app->configPath('foundry.php'),
                $this->packagePath('config/stripe.php') => $this->app->configPath('stripe.php'),
            ], 'foundry-config');

            $this->publishes([
                $this->packagePath('database/migrations') => $this->app->databasePath('migrations'),
            ], 'foundry-migrations');

            $this->publishes([
                $this->packagePath('src/Providers/FortifyServiceProvider.php') => app_path('Providers/FortifyServiceProvider.php'),
            ], 'foundry-fortify-provider');

            $this->publishes([
                $this->packagePath('public') => public_path('statics'),

                $this->packageStubPath('database') => $this->app->databasePath(),

                $this->packagePath('resources/views/emails') => resource_path('views/emails'),
                $this->packagePath('resources/views/pdfs') => resource_path('views/pdfs'),
                $this->packagePath('resources/views/shortcodes') => resource_path('views/shortcodes'),
                $this->packagePath('resources/views/includes') => resource_path('views/includes'),
                $this->packagePath('resources/views/layouts') => resource_path('views/layouts'),
                $this->packageStubPath('views/app.blade.php') => resource_path('views/app.blade.php'),

                $this->packageStubPath('theme') => $this->app->basePath('themes/foundation'),

                $this->packageStubPath('models') => app_path('Models'),

                $this->packagePath('resources/lang') => resource_path('lang'),
            ], 'foundry-assets');
        }
    }

    /**
     * Register the package route middlewares.
     *
     * @return void
     */
    protected function registerRouteMiddleware()
    {
        Route::aliasMiddleware('configure.guard', Middleware\ConfigureGuard::class);
        Route::aliasMiddleware('preserve.json.whitespace', Middleware\PreserveJsonWhitespace::class);
        Route::aliasMiddleware('resolve.currency', Middleware\ResolveCurrency::class);

        Route::aliasMiddleware('resolve.ip', Middleware\ResolveIpAddress::class);
    }

    /**
     * Register the package's commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([
            Commands\InstallCommand::class,
            Commands\CheckCanceledSubscriptions::class,
            Commands\CheckGracePeriodSubscriptions::class,
            Commands\SubscriptionsRenew::class,
            Commands\ResetSubscriptionsUsages::class,
            Commands\ResumeSubscriptions::class,
            Commands\MigrateSubscriptionFeatures::class,
            Commands\MigrateOrderCommand::class,
            Commands\LangParseCommand::class,
            Commands\UpdateExchangeRates::class,
        ]);
    }

    protected function registerRoutes()
    {
        Route::middleware('web')->group(function () {
            $this->loadRoutesFrom($this->packagePath('routes/web.php'));
        });
    }

    protected function defineManagementRoutes()
    {
        if (app()->routesAreCached()) {
            return;
        }

        if (! $this->app->runningInConsole()) {
            Route::group(['prefix' => 'license'], function () {
                Route::get('/manage', [ApplicationState::class, 'manage'])
                    ->middleware('web')
                    ->name('license-manage');
                Route::post('/update', [ApplicationState::class, 'update'])
                    ->middleware('web')
                    ->name('license-update');
            });
        }
    }

    /**
     * Initialize application core state.
     *
     * @return void
     */
    protected function bootstrapApplicationCore()
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        if ($this->app->make(StateInterface::class)->isStable()) {
            return;
        }

        if (! $this->isInitialized()) {
            return;
        }

        if ($this->isManagementRoute()) {
            return;
        }

        $loader = $this->app->make(ConfigurationInterface::class);

        if (! $loader->isValid()) {
            logger()->error('Core initialization failed.');
            $this->haltApplication();
        }

        $this->app->instance('system.ready', true);
        $this->app->instance('core.loader', $loader);
    }

    /**
     * Register core middleware.
     */
    protected function registerCoreMiddleware(): void
    {
        $kernel = $this->app->make('Illuminate\Contracts\Http\Kernel');
        $kernel->pushMiddleware(ApplicationState::class);
        $kernel->pushMiddleware(ResponseOptimizer::class);
        $kernel->pushMiddleware(Middleware\ResolveIpAddress::class);
    }

    protected function isManagementRoute()
    {
        try {
            if (! $this->app->bound('request')) {
                return false;
            }

            $request = $this->app->make('request');

            if (! $request || ! method_exists($request, 'is')) {
                return false;
            }

            return $request->is('*license/manage') || $request->is('*license/update') || $request->is('*install*');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function isInitialized()
    {
        $flag = base_path('storage/.installed');

        return file_exists($flag);
    }

    protected function haltApplication()
    {
        if ($this->isManagementRoute()) {
            return;
        }

        try {
            $htmlPath = $this->packagePath('resources/views/license-required.html');
            if (file_exists($htmlPath)) {
                $html = file_get_contents($htmlPath);
            } else {
                throw new \Exception('Required HTML file not found.');
            }
        } catch (\Throwable $e) {
            $html = '<!DOCTYPE html><html><body><h1>Application Error</h1><p>Initialization failed.</p></body></html>';
        }

        http_response_code(403);
        echo $html;
        exit();
    }

    protected function packagePath(string $path)
    {
        return __DIR__.'/../../'.$path;
    }

    protected function packageStubPath(string $path)
    {
        return __DIR__.'/../../stubs/'.$path;
    }
}
