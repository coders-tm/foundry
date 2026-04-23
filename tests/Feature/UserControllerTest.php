<?php

namespace Foundry\Tests\Feature;

use Foundry\Models\Coupon;
use Foundry\Models\File;
use Foundry\Models\PaymentMethod;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Feature;
use Foundry\Models\Subscription\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\Admin;
use Workbench\App\Models\User;

class UserControllerTest extends FeatureTestCase
{
    use RefreshDatabase;

    /** @var Admin */
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Admin $this->admin */
        $this->admin = Admin::factory()->admin()->create();
        $this->actingAs($this->admin, 'admin');
    }

    public function test_it_can_list_users()
    {
        User::factory()->count(5)->create();

        $response = $this->getJson(route('users.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'first_name', 'last_name', 'email'],
                ],
            ]);
    }

    public function test_index_loads_subscription_info_for_users()
    {
        $plan = Plan::factory()->create([
            'price' => 2900,
            'trial_days' => 0,
        ]);

        $user = User::factory()->create([
            'status' => 'active', // Ensure user is active to pass onlyMember() filter
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
    }

    public function test_it_can_show_user_with_subscription_info()
    {
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
    }

    public function test_show_user_without_subscription()
    {
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
    }

    public function test_it_can_create_user_without_plan()
    {
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
    }

    public function test_it_can_create_user_with_subscription()
    {
        $plan = Plan::factory()->create([
            'price' => 2900,
            'trial_days' => 0,
        ]);

        // Create payment method directly instead of using factory
        $paymentMethod = PaymentMethod::create([
            'name' => 'test-payment',
            'label' => 'Test Payment Method',
            'provider' => 'stripe',
            'active' => true,
        ]);

        $userData = [
            'email' => 'subscriber@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'plan' => $plan->id,
            'payment_method' => $paymentMethod->id,
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

        // Reload user with subscriptions
        $user->load('subscriptions');

        // Check subscription was created with correct plan
        $this->assertEquals(1, $user->subscriptions->count(), 'User should have exactly 1 subscription');

        $subscription = $user->subscriptions->first();
        $this->assertNotNull($subscription, 'Subscription should exist');
        $this->assertEquals($plan->id, $subscription->plan_id, 'Subscription should have correct plan');
        $this->assertEquals('default', $subscription->type, 'Subscription type should be default');

        // Subscription should exist (even if pending payment)
        $this->assertNotNull($user->subscription());
    }

    public function test_store_requires_payment_method_when_plan_provided()
    {
        $plan = Plan::factory()->create();

        $userData = [
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'plan' => $plan->id,
            // Missing payment_method
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
    }

    public function test_it_can_create_user_with_subscription_and_coupon()
    {
        $plan = Plan::factory()->create([
            'price' => 2900,
            'trial_days' => 0,
        ]);

        // Create coupon directly instead of using factory
        $coupon = Coupon::create([
            'promotion_code' => 'TESTCODE',
            'discount_type' => 'percentage',
            'value' => 20,
            'is_active' => true,
            'duration' => 'once',
        ]);

        // Create payment method directly instead of using factory
        $paymentMethod = PaymentMethod::create([
            'name' => 'test-payment-coupon',
            'label' => 'Test Payment Method',
            'provider' => 'stripe',
            'active' => true,
        ]);

        $userData = [
            'email' => 'coupon@example.com',
            'first_name' => 'Coupon',
            'last_name' => 'User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'plan' => $plan->id,
            'payment_method' => $paymentMethod->id,
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

        // Verify subscription was created successfully
        // Note: Coupon redemption logic is handled by the application's subscription creation process
        $this->assertEquals($plan->id, $subscription->plan_id);
    }

    public function test_it_can_update_user_with_subscription_info()
    {
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
    }

    public function test_subscription_info_includes_feature_usages()
    {
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

        // Detach any default features added by factory and attach only our test feature
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
    }

    public function test_subscription_info_shows_canceled_message()
    {
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

        // Cancel the subscription
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
    }

    public function test_it_validates_required_fields_on_store()
    {
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
    }

    public function test_it_validates_unique_email_on_store()
    {
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
    }

    public function test_it_can_delete_user()
    {
        $user = User::factory()->create();

        $response = $this->deleteJson(route('users.destroy', $user->id));

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        // The message uses Laravel's trans_choice which results in a formatted pluralization string
        $message = $response->json('message');
        $this->assertStringContainsString('been deleted successfully', strtolower($message));

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    public function test_it_can_get_user_options()
    {
        User::factory()->count(3)->create();

        $response = $this->postJson(route('users.options'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'first_name', 'last_name', 'email'],
                ],
            ]);
    }

    public function test_it_can_import_users()
    {
        // Create CSV file with proper headers
        $csvData = [
            ['First Name', 'Surname', 'Gender', 'Email Address', 'Status', 'Password', 'Created At', 'Plan', 'Trial Ends At', 'Address Line1', 'Country', 'State', 'State Code', 'City'],
            ['Import', 'User1', 'Male', 'import1@example.com', 'active', 'password123', '2024-01-01 00:00:00', 'Basic', '2024-02-01 00:00:00', '123 Main St', 'US', 'California', 'CA', 'Los Angeles'],
            ['Import', 'User2', 'Female', 'import2@example.com', 'active', 'password123', '2024-01-01 00:00:00', 'Basic', '2024-02-01 00:00:00', '456 Oak Ave', 'US', 'California', 'CA', 'San Francisco'],
        ];

        $csvContent = array_map(function ($row) {
            return implode(',', $row);
        }, $csvData);

        $csvString = implode("\n", $csvContent);

        // Create a file record
        $file = File::create([
            'original_name' => 'test_users.csv',
            'path' => 'temp/test_users.csv',
            'mime' => 'text/csv',
            'size' => strlen($csvString),
        ]);

        // Save actual CSV content to storage
        Storage::put($file->path, $csvString);

        $response = $this->postJson(route('users.import'), [
            'file' => $file->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        // Clean up
        Storage::delete($file->path);
    }

    public function test_it_can_change_user_active_status()
    {
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
    }

    public function test_it_can_add_notes_to_user()
    {
        $user = User::factory()->create();

        $noteData = [
            'message' => 'This is a test note for the user.',
        ];

        $response = $this->postJson(route('users.notes', $user->id), $noteData);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data']);

        // Verify the response contains the created note data
        $responseData = $response->json('data');
        $this->assertNotNull($responseData);
        $this->assertEquals('This is a test note for the user.', $responseData['message']);

        $this->assertDatabaseHas('logs', [
            'logable_type' => 'User',
            'logable_id' => $user->id,
            'message' => 'This is a test note for the user.',
            'type' => 'notes',
        ]);
    }

    public function test_it_can_mark_user_as_paid()
    {
        $plan = Plan::factory()->create([
            'price' => 2900,
            'trial_days' => 0,
        ]);

        $paymentMethod = PaymentMethod::factory()->active()->create();

        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->postJson(route('users.mark-as-paid', $user->id), [
            'payment_method' => $paymentMethod->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);
    }

    public function test_it_can_send_reset_password_request()
    {
        $user = User::factory()->create([
            'email' => 'resetpassword@example.com',
        ]);

        $response = $this->postJson(route('users.reset-password-request', $user->id));

        $response->assertStatus(200)
            ->assertJsonStructure(['message']);

        // Verify password reset token was created
        // Note: Uses 'password_reset_tokens' table in modern Laravel
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'resetpassword@example.com',
        ]);
    }
}
