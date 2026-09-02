<?php

use Foundry\Enum\AppStatus;
use Foundry\Models\Admin;
use Foundry\Models\User;
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(BaseTestCase::class)->use(RefreshDatabase::class);

it('user last login at is updated on login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'last_login_at' => null,
        'status' => AppStatus::ACTIVE,
    ]);

    expect($user->last_login_at)->toBeNull();

    $response = $this->postJson('/auth/user/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(200);

    $user = $user->fresh();
    expect($user->last_login_at)->not->toBeNull();
    expect(now()->diffInSeconds($user->last_login_at) < 60)->toBeTrue();
});

it('admin last login at is updated on login', function () {
    $admin = Admin::factory()->create([
        'password' => bcrypt('password'),
        'last_login_at' => null,
        'is_active' => true,
    ]);

    expect($admin->last_login_at)->toBeNull();

    $response = $this->postJson('/auth/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertStatus(200);

    $admin = $admin->fresh();
    expect($admin->last_login_at)->not->toBeNull();
    expect(now()->diffInSeconds($admin->last_login_at) < 60)->toBeTrue();
});
