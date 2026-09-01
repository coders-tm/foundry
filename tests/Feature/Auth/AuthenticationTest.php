<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class);

it('can render login screen', function () {
    $response = $this->get(route('admin.login'));
    $response->assertOk();
});

it('admins can authenticate using the login screen', function () {
    $admin = \Foundry\Models\Admin::factory()->create(['is_active' => true]);

    $response = $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated('admin');
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

it('admins cannot authenticate with invalid password', function () {
    $admin = \Foundry\Models\Admin::factory()->create(['is_active' => true]);

    $this->post(route('admin.login.store'), [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('admin');
});

it('admins can logout', function () {
    $admin = \Foundry\Models\Admin::factory()->create(['is_active' => true]);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

    $this->assertGuest('admin');
    $response->assertRedirect(route('admin.login'));
});
