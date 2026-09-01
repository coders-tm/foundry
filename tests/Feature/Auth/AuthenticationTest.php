<?php

use Foundry\Models\Admin;
use Foundry\Tests\Feature\FeatureTestCase;

uses(FeatureTestCase::class);

it('can render login screen', function () {
    $response = $this->get(route('admin.login'));
    $response->assertOk();
});

it('admins can authenticate using the login screen', function () {
    $admin = Admin::factory()->create(['is_active' => true]);

    $response = $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated('admin');
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

it('admins cannot authenticate with invalid password', function () {
    $admin = Admin::factory()->create(['is_active' => true]);

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('admin');
});

it('admins can logout', function () {
    $admin = Admin::factory()->create(['is_active' => true]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

    $this->assertGuest('admin');
    $response->assertRedirect(route('admin.login'));
});
