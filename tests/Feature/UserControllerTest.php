<?php

use Foundry\Models\Coupon;
use Foundry\Models\File;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Feature;
use Foundry\Models\Subscription\Plan;
use Foundry\Services\PaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\Admin;
use Workbench\App\Models\User;

uses(\Foundry\Tests\Feature\FeatureTestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var Admin $this->admin */
    $this->admin = Admin::factory()->admin()->create();
    $this->actingAs($this->admin, 'admin');
});

it('can list users', function () {
    User::factory()->count(5)->create();

    $response = $this->getJson(route('users.index'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'first_name', 'last_name', 'email'],
            ],
        ]);
});

it('loads subscription info for users on index', function () {
    $plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 0,
    ]);

    $user = User::factory()->create([
        'status' => 'active',
    ]);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $response = $this->getJson(route('users.index'));

    $response->assertStatus(200);

    $userData = collect($response->json('data'))->firstWhere('id', $user->id);

    $this->assertNotNull($userData);
    $this->assertArrayHasKey('subscription', $userData);
    $this->assertEquals($subscription->id, $userData['subscription']['id']);
});

it('can show user with subscription info', function () {
    $plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 0,
    ]);

    $user = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $response = $this->getJson(route('users.show', $user->id));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'first_name',
            'last_name',
            'email',
            'subscription' => [
                'id',
                'plan' => [
                    'id',
                    'label',
                ],
                'status',
                'active',
                'canceled',
                'ended',
                'expired',
                'downgrade',
                'on_grace_period',
                'canceled_on_grace_period',
                'has_incomplete_payment',
                'has_due',
                'on_trial',
                'is_valid',
                'expires_at',
                'trial_ends_at',
                'invoice',
                'metadata',
            ],
        ]);

    $this->assertEquals($subscription->id, $response->json('subscription.id'));
});

it('shows user without subscription', function () {
    $user = User::factory()->create();

    $response = $this->getJson(route('users.show', $user->id));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'first_name',
            'last_name',
            'email',
            'subscription',
        ]);

    $this->assertNull($response->json('subscription'));
});

it('can create user without plan', function () {
    $userData = [
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'address' => [
            'line1' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US',
        ],
    ];

    $response = $this->postJson(route('users.store'), $userData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'email', 'first_name', 'last_name'],
            'message',
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
});

it('can create user with subscription', function () {
    $plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 0,
    ]);

    $userData = [
        'email' => 'subscriber@example.com',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => $plan->id,
        'payment_method' => PaymentProvider::STRIPE,
        'address' => [
            'line1' => '456 Oak Ave',
            'city' => 'Los Angeles',
            'postal_code' => '90001',
            'country' => 'US',
        ],
    ];

    $response = $this->postJson(route('users.store'), $userData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'email',
                'subscription' => [
                    'id',
                    'plan' => [
                        'id',
                        'label',
                    ],
                    'status',
                ],
            ],
            'message',
        ]);

    $user = User::where('email', 'subscriber@example.com')->first();
    $this->assertNotNull($user);

    $user->load('subscriptions');

    $this->assertEquals(1, $user->subscriptions->count(), 'User should have exactly 1 subscription');

    $subscription = $user->subscriptions->first();
    $this->assertNotNull($subscription, 'Subscription should exist');
    $this->assertEquals($plan->id, $subscription->plan_id, 'Subscription should have correct plan');
    $this->assertEquals('default', $subscription->type, 'Subscription type should be default');

    $this->assertNotNull($user->subscription());
});

it('requires payment method when plan provided', function () {
    $plan = Plan::factory()->create();

    $userData = [
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => $plan->id,
        'address' => [
            'line1' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US',
        ],
    ];

    $response = $this->postJson(route('users.store'), $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_method']);
});

it('can create user with subscription and coupon', function () {
    $plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 0,
    ]);

    $coupon = Coupon::create([
        'promotion_code' => 'TESTCODE',
        'discount_type' => 'percentage',
        'value' => 20,
        'is_active' => true,
        'duration' => 'once',
    ]);

    $userData = [
        'email' => 'coupon@example.com',
        'first_name' => 'Coupon',
        'last_name' => 'User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => $plan->id,
        'payment_method' => PaymentProvider::STRIPE,
        'promotion_code' => 'TESTCODE',
        'address' => [
            'line1' => '789 Pine St',
            'city' => 'Chicago',
            'postal_code' => '60601',
            'country' => 'US',
        ],
    ];

    $response = $this->postJson(route('users.store'), $userData);

    $response->assertStatus(200);

    $user = User::where('email', 'coupon@example.com')->first();
    $this->assertNotNull($user);

    $subscription = $user->subscription();
    $this->assertNotNull($subscription);

    $this->assertEquals($plan->id, $subscription->plan_id);
});

it('can update user with subscription info', function () {
    $plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 0,
    ]);

    $user = User::factory()->create();

    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $updateData = [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => $user->email,
        'address' => [
            'line1' => '123 Updated St',
            'city' => 'Updated City',
            'postal_code' => '12345',
            'country' => 'US',
        ],
    ];

    $response = $this->putJson(route('users.update', $user->id), $updateData);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'first_name',
                'last_name',
                'subscription' => [
                    'id',
                    'status',
                    'usages',
                ],
            ],
            'message',
        ])
        ->assertJson([
            'data' => [
                'first_name' => 'Updated',
                'last_name' => 'Name',
            ],
            'message' => 'User account has been updated successfully!',
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'first_name' => 'Updated',
        'last_name' => 'Name',
    ]);
});

it('includes feature usages in subscription info', function () {
    $feature = Feature::factory()->create([
        'slug' => 'api-calls',
        'label' => 'API Calls',
        'type' => 'integer',
        'resetable' => true,
    ]);

    $plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 0,
    ]);

    $plan->features()->detach();
    $plan->features()->attach($feature, ['value' => 1000]);

    $user = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $subscription->recordFeatureUsage('api-calls', 150);

    $response = $this->getJson(route('users.show', $user->id));

    $response->assertStatus(200);

    $usages = $response->json('subscription.usages');
    $this->assertIsArray($usages);
    $this->assertCount(1, $usages);

    $usage = $usages[0];
    $this->assertEquals('api-calls', $usage['slug']);
    $this->assertEquals('API Calls', $usage['label']);
    $this->assertEquals(150, $usage['used']);
    $this->assertEquals(1000, $usage['value']);
});

it('shows canceled message in subscription info', function () {
    $plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 0,
    ]);

    $user = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'expires_at' => now()->addDays(7),
    ]);

    $subscription->cancel();

    $response = $this->getJson(route('users.show', $user->id));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'subscription' => [
                'id',
                'status',
                'canceled',
            ],
        ]);

    $id = $response->json('subscription.id');
    $this->assertNotNull($id);
    $this->assertTrue($response->json('subscription.canceled'));
});

it('validates required fields on store', function () {
    $response = $this->postJson(route('users.store'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'email',
            'first_name',
            'last_name',
            'address.line1',
            'address.city',
            'address.postal_code',
            'address.country',
        ]);
});

it('validates unique email on store', function () {
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $userData = [
        'email' => 'existing@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'address' => [
            'line1' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US',
        ],
    ];

    $response = $this->postJson(route('users.store'), $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('can delete user', function () {
    $user = User::factory()->create();

    $response = $this->deleteJson(route('users.destroy', $user->id));

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);

    $message = $response->json('message');
    $this->assertStringContainsString('been deleted successfully', strtolower($message));

    $this->assertSoftDeleted('users', [
        'id' => $user->id,
    ]);
});

it('can get user options', function () {
    User::factory()->count(3)->create();

    $response = $this->postJson(route('users.options'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'first_name', 'last_name', 'email'],
            ],
        ]);
});

it('can import users', function () {
    $csvData = [
        ['First Name', 'Surname', 'Gender', 'Email Address', 'Status', 'Password', 'Created At', 'Plan', 'Trial Ends At', 'Address Line1', 'Country', 'State', 'State Code', 'City'],
        ['Import', 'User1', 'Male', 'import1@example.com', 'active', 'password123', '2024-01-01 00:00:00', 'Basic', '2024-02-01 00:00:00', '123 Main St', 'US', 'California', 'CA', 'Los Angeles'],
        ['Import', 'User2', 'Female', 'import2@example.com', 'active', 'password123', '2024-01-01 00:00:00', 'Basic', '2024-02-01 00:00:00', '456 Oak Ave', 'US', 'California', 'CA', 'San Francisco'],
    ];

    $csvContent = array_map(function ($row) {
        return implode(',', $row);
    }, $csvData);

    $csvString = implode("\n", $csvContent);

    $file = File::create([
        'original_name' => 'test_users.csv',
        'path' => 'temp/test_users.csv',
        'mime' => 'text/csv',
        'size' => strlen($csvString),
    ]);

    Storage::put($file->path, $csvString);

    $response = $this->postJson(route('users.import'), [
        'file' => $file->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);

    Storage::delete($file->path);
});

it('can change user active status', function () {
    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $response = $this->postJson(route('users.change-active', $user->id), [
        'is_active' => false,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'is_active' => false,
    ]);
});

it('can add notes to user', function () {
    $user = User::factory()->create();

    $noteData = [
        'message' => 'This is a test note for the user.',
    ];

    $response = $this->postJson(route('users.notes', $user->id), $noteData);

    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'data']);

    $responseData = $response->json('data');
    $this->assertNotNull($responseData);
    $this->assertEquals('This is a test note for the user.', $responseData['message']);

    $this->assertDatabaseHas('logs', [
        'logable_type' => 'User',
        'logable_id' => $user->id,
        'message' => 'This is a test note for the user.',
        'type' => 'notes',
    ]);
});

it('can mark user as paid', function () {
    $plan = Plan::factory()->create([
        'price' => 2900,
        'trial_days' => 0,
    ]);

    $user = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $response = $this->postJson(route('users.mark-as-paid', $user->id), [
        'payment_method' => PaymentProvider::STRIPE,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);
});

it('can send reset password request', function () {
    $user = User::factory()->create([
        'email' => 'resetpassword@example.com',
    ]);

    $response = $this->postJson(route('users.reset-password-request', $user->id));

    $response->assertStatus(200)
        ->assertJsonStructure(['message']);

    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => 'resetpassword@example.com',
    ]);
});
