<?php

namespace Foundry\Tests\Feature;

use Foundry\Enum\AppStatus;
use Foundry\Models\Admin;
use Foundry\Models\User;
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LastLoginatTest extends BaseTestCase
{
    use RefreshDatabase;

    public function test_user_last_login_at_is_updated_on_login()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'last_login_at' => null,
            'status' => AppStatus::ACTIVE,
        ]);

        $this->assertNull($user->last_login_at);

        $response = $this->postJson('/auth/user/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200);

        $user = $user->fresh();
        $this->assertNotNull($user->last_login_at, 'User last_login_at should not be null');
        $this->assertTrue(now()->diffInSeconds($user->last_login_at) < 60);
    }

    public function test_admin_last_login_at_is_updated_on_login()
    {
        $admin = Admin::factory()->create([
            'password' => bcrypt('password'),
            'last_login_at' => null,
            'is_active' => true,
        ]);

        $this->assertNull($admin->last_login_at);

        $response = $this->postJson('/auth/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200);

        $admin = $admin->fresh();
        $this->assertNotNull($admin->last_login_at, 'Admin last_login_at should not be null');
        $this->assertTrue(now()->diffInSeconds($admin->last_login_at) < 60);
    }
}
