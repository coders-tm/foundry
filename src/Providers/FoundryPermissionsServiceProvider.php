<?php

namespace Foundry\Providers;

use Foundry\Foundry;
use Foundry\Models;
use Foundry\Policies;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class FoundryPermissionsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        try {
            $permissions = Cache::rememberForever('foundry.permissions.all', function () {
                return Models\Permission::get()->pluck('scope')->toArray();
            });

            collect($permissions)->each(function ($permission) {
                Gate::define($permission, function ($user) use ($permission) {
                    return $user->hasPermission($permission);
                });
            });

            // Blade directives
            Blade::directive('group', function ($group, $guard = 'users') {
                return "if(guard({$guard}) && user()->hasGroup({$group})) :"; // return this if statement inside php tag
            });

            Blade::directive('endgroup', function ($group) {
                return 'endif;'; // return this endif statement inside php tag
            });
        } catch (\Throwable $e) {
            Log::error('Error booting FoundryPermissionsServiceProvider: '.$e->getMessage());
        }

        // Auto register policies
        Gate::policy(Foundry::$userModel, Policies\UserPolicy::class);
        Gate::policy(Foundry::$adminModel, Policies\AdminPolicy::class);
        Gate::policy(Foundry::$supportTicketModel, Policies\SupportTicketPolicy::class);
        Gate::policy(Foundry::$orderModel, Policies\OrderPolicy::class);
        Gate::policy(Foundry::$planModel, Policies\Subscription\PlanPolicy::class);
        Gate::policy(Foundry::$couponModel, Policies\CouponPolicy::class);
        Gate::policy(Models\Blog::class, Policies\BlogPolicy::class);
        Gate::policy(Models\Group::class, Policies\GroupPolicy::class);
        Gate::policy(Models\Notification::class, Policies\SettingPolicy::class);
        Gate::policy(Models\Setting::class, Policies\SettingPolicy::class);
        Gate::policy(Models\ReportExport::class, Policies\ReportExportPolicy::class);
        Gate::policy(Models\PaymentMethod::class, Policies\SettingPolicy::class);
        Gate::policy(Models\Tax::class, Policies\SettingPolicy::class);
        Gate::policy(Models\File::class, Policies\FilePolicy::class);
    }
}
