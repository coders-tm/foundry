<?php

namespace Foundry\Tests\Feature\Notifications;

use Database\Seeders\NotificationSeeder;
use Foundry\Models\Notification;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class NotificationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing notifications
        Notification::query()->delete();
    }

    #[Test]
    public function it_loads_notification_templates_from_blade_files()
    {
        // Run the seeder
        $seeder = new NotificationSeeder;
        $seeder->run();

        // Verify notifications were created
        $this->assertGreaterThan(0, Notification::count(), 'No notifications were seeded');

        // Verify specific notification types exist
        $this->assertTrue(
            Notification::where('type', 'user:signup')->exists(),
            'user:signup notification was not created'
        );

        $this->assertTrue(
            Notification::where('type', 'admin:hold-release')->exists(),
            'admin:hold-release was not created'
        );

        $this->assertTrue(
            Notification::where('type', 'admin:import-completed')->exists(),
            'admin:import-completed was not created'
        );
    }

    #[Test]
    public function it_parses_metadata_from_blade_comments_correctly()
    {
        // Run the seeder
        $seeder = new NotificationSeeder;
        $seeder->run();

        // Check a specific notification
        $notification = Notification::where('type', 'user:signup')->first();

        $this->assertNotNull($notification, 'user:signup notification not found');
        $this->assertEquals('Signup', $notification->label);
        $this->assertEquals('Welcome to {{$app->name}} - Your Subscription Details', $notification->subject);
        $this->assertTrue($notification->is_default);
        $this->assertNotEmpty($notification->content);
    }

    #[Test]
    public function it_parses_import_completed_notification()
    {
        // Run the seeder
        $seeder = new NotificationSeeder;
        $seeder->run();

        // Check import completed notification
        $notification = Notification::where('type', 'admin:import-completed')->first();

        $this->assertNotNull($notification, 'admin:import-completed notification not found');
        $this->assertEquals('Import Completed', $notification->label);
        $this->assertStringContainsString('[{{ $app->name }}] {{ $import->model }} import completed', $notification->subject);

        // Check for shortcodes (normalized for whitespace)
        $normalizedContent = preg_replace('/\s+/', ' ', $notification->content);
        $this->assertStringContainsString('{{ $import->successed }}', $normalizedContent);
        $this->assertStringContainsString('{{ $import->failed }}', $normalizedContent);
        $this->assertStringContainsString('{{ $import->skipped }}', $normalizedContent);
    }

    #[Test]
    public function it_updates_existing_notifications_without_duplicates()
    {
        // Create an existing notification
        Notification::create([
            'label' => 'Old Label',
            'subject' => 'Old Subject',
            'type' => 'user:signup',
            'is_default' => false,
            'content' => 'Old content',
        ]);

        $this->assertEquals(1, Notification::count());

        // Run the seeder
        $seeder = new NotificationSeeder;
        $seeder->run();

        // Should update, not duplicate
        $notification = Notification::where('type', 'user:signup')->first();
        $this->assertEquals('Signup', $notification->label);
        $this->assertNotEquals('Old Label', $notification->label);

        // Should have all notifications without duplicates
        $totalNotifications = Notification::count();
        $this->assertGreaterThan(1, $totalNotifications);

        // Verify no duplicates
        $uniqueTypes = Notification::pluck('type')->unique()->count();
        $this->assertEquals($totalNotifications, $uniqueTypes, 'Found duplicate notification types');
    }

    #[Test]
    public function all_notification_types_are_seeded()
    {
        // Run the seeder
        $seeder = new NotificationSeeder;
        $seeder->run();

        // Expected notification types
        $expectedTypes = [
            // User notifications
            'user:invoice-sent',
            'user:signup',
            'user:subscription-cancel',
            'user:subscription-canceled',
            'user:subscription-downgrade',
            'user:subscription-expired',
            'user:subscription-renewed',
            'user:subscription-upgraded',
            'user:support-ticket-notification',
            'user:support-ticket-confirmation',
            'user:support-ticket-reply-notification',
            'user:payment-success',
            'user:payment-failed',
            'user:partial-refund',

            // Common notifications
            'common:user-login',
            'common:password-reset-request',

            // Admin notifications
            'admin:support-ticket-reply-notification',
            'admin:subscription-expired',
            'admin:subscription-cancel',
            'admin:hold-release',
            'admin:support-ticket-notification',
            'admin:new-account',
            'admin:contact-us-notification',
            'admin:import-completed',
            'admin:payment-failed',
            'admin:refund-processed',
        ];

        $seededTypes = Notification::pluck('type')->toArray();

        foreach ($expectedTypes as $type) {
            $this->assertContains(
                $type,
                $seededTypes,
                "Notification type '{$type}' was not seeded"
            );
        }

        $this->assertCount(count($expectedTypes), $seededTypes, 'Unexpected number of notifications seeded');
    }

    #[Test]
    public function it_handles_missing_optional_metadata_fields()
    {
        // Run the seeder
        $seeder = new NotificationSeeder;
        $seeder->run();

        // All notifications should have required fields
        $notifications = Notification::all();

        foreach ($notifications as $notification) {
            $this->assertNotEmpty($notification->type, 'Notification missing type');
            $this->assertNotEmpty($notification->label, 'Notification missing label');
            $this->assertNotNull($notification->is_default, 'Notification missing is_default');
        }
    }
}
