<?php

namespace Foundry\Tests\Feature\Auth;

use Foundry\Models\Admin;
use Foundry\Tests\Feature\FeatureTestCase;

class AuthenticationTest extends FeatureTestCase
{
    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
    }

    public function test_admins_can_authenticate_using_the_login_screen()
    {
        /** @var Admin $admin */
        $admin = Admin::factory()->create(['is_active' => true]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticated('admin');
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_admins_can_not_authenticate_with_invalid_password()
    {
        $admin = Admin::factory()->create(['is_active' => true]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('admin');
    }

    public function test_admins_can_logout()
    {
        /** @var Admin $admin */
        $admin = Admin::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

        $this->assertGuest('admin');
        $response->assertRedirect(route('admin.login'));
    }
}
