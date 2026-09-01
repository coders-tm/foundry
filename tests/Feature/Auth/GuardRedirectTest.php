<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Route::get('/dashboard', function () {
        return 'user dashboard';
    })->middleware(['web', 'auth:user']);

    \Illuminate\Support\Facades\Route::get('/admin', function () {
        return 'admin dashboard';
    })->middleware(['web', 'auth:admin']);

    \Illuminate\Support\Facades\Route::get('/login', function () {
        return 'login page';
    })->middleware(['web', 'guest:user'])->name('login');

    \Illuminate\Support\Facades\Route::get('/admin/login', function () {
        return 'admin login page';
    })->middleware(['web', 'guest:admin'])->name('admin.login');
});

it('guard user redirects to user login', function () {
    $this->get('/dashboard')
        ->assertRedirect(route('login'));
});

it('guard admin redirects to admin login', function () {
    $this->get('/admin')
        ->assertRedirect(route('admin.login'));
});

it('guest admin redirects authenticated admin to admin home', function () {
    $admin = \Foundry\Models\Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/admin/login')
        ->assertRedirect('/admin');
});

it('guest user redirects authenticated user to user home', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user, 'user')
        ->get('/login')
        ->assertRedirect('/dashboard');
});
