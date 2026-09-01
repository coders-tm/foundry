<?php

use App\Models\User;
use Foundry\Models\Admin;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Facades\Route;

uses(FeatureTestCase::class);

beforeEach(function () {
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
});

it('guard user redirects to user login', function () {
    $this->get('/dashboard')
        ->assertRedirect(route('login'));
});

it('guard admin redirects to admin login', function () {
    $this->get('/admin')
        ->assertRedirect(route('login'));
});

it('guest admin redirects authenticated admin to admin home', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/login')
        ->assertRedirect('/admin');
});

it('guest user redirects authenticated user to user home', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'user')
        ->get('/login')
        ->assertRedirect('/dashboard');
});
