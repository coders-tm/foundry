<?php

namespace Foundry\Tests\Feature\Auth;

use App\Models\User;
use Foundry\Models\Admin;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Facades\Route;

class GuardRedirectTest extends FeatureTestCase
{
    protected function defineRoutes($router)
    {
        Route::get('/dashboard', function () {
            return 'user dashboard';
        })->middleware(['web', 'auth:user']);

        Route::get('/admin', function () {
            return 'admin dashboard';
        })->middleware(['web', 'auth:admin']);

        Route::get('/login', function () {
            return 'login page';
        })->middleware(['web', 'guest:user'])->name('login');

        Route::get('/admin/login', function () {
            return 'admin login page';
        })->middleware(['web', 'guest:admin'])->name('admin.login');
    }

    public function test_guard_user_redirects_to_user_login()
    {
        $this->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_guard_admin_redirects_to_admin_login()
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.login'));
    }

    public function test_guest_admin_redirects_authenticated_admin_to_admin_home()
    {
        /** @var Admin $admin */
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin/login')
            ->assertRedirect('/admin');
    }

    public function test_guest_user_redirects_authenticated_user_to_user_home()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user, 'user')
            ->get('/login')
            ->assertRedirect('/dashboard');
    }
}
