<?php

use Foundry\Models\Admin;
use Foundry\Models\User;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->admin = Admin::factory()->create([
        'is_super_admin' => true,
    ]);
});

it('can view user wallet balance', function () {
    $this->user->creditWallet(150.00, 'test', 'Test balance');

    $response = $this->actingAs($this->admin, 'admin')
        ->getJson("/admin/users/{$this->user->id}/wallet/balance");

    $response->assertStatus(200)
        ->assertJson([
            'balance' => 150,
            'currency' => 'USD',
        ]);
});

it('can view user wallet transactions', function () {
    $this->user->creditWallet(100.00, 'test', 'First transaction');
    $this->user->creditWallet(50.00, 'test', 'Second transaction');
    $this->user->debitWallet(25.00, 'test', 'Third transaction');

    $response = $this->actingAs($this->admin, 'admin')
        ->getJson("/admin/users/{$this->user->id}/wallet/transactions");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('can credit user wallet', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->postJson("/admin/users/{$this->user->id}/wallet/credit", [
            'amount' => 100.00,
            'description' => 'Admin credit',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Wallet credited successfully',
            'balance' => 100,
        ]);

    expect($this->user->fresh()->getWalletBalance())->toEqual(100);
});

it('can debit user wallet', function () {
    $this->user->creditWallet(200.00, 'test', 'Initial balance');

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson("/admin/users/{$this->user->id}/wallet/debit", [
            'amount' => 50.00,
            'description' => 'Admin debit',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Wallet debited successfully',
            'balance' => 150,
        ]);

    expect($this->user->fresh()->getWalletBalance())->toEqual(150);
});

it('cannot debit more than wallet balance', function () {
    $this->user->creditWallet(50.00, 'test', 'Initial balance');

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson("/admin/users/{$this->user->id}/wallet/debit", [
            'amount' => 100.00,
            'description' => 'Admin debit',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('requires valid amount for credit', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->postJson("/admin/users/{$this->user->id}/wallet/credit", [
            'amount' => -50.00,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('requires valid amount for debit', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->postJson("/admin/users/{$this->user->id}/wallet/debit", [
            'amount' => 0,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

it('prevents non admin from accessing admin wallet routes', function () {
    /** @var User $otherUser */
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser, 'user')
        ->getJson("/admin/users/{$this->user->id}/wallet/balance");

    $response->assertStatus(401);
});
