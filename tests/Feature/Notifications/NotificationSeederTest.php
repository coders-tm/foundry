<?php

uses(\Foundry\Tests\TestCase::class)
    ->use(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Foundry\Models\Notification::query()->delete();
});

it('loads notification templates from blade files', function () {
    $seeder = new \Database\Seeders\NotificationSeeder;
    $seeder->run();

    $this->assertGreaterThan(0, \Foundry\Models\Notification::count());
    $this->assertTrue(\Foundry\Models\Notification::where('type', 'user:signup')->exists());
    $this->assertTrue(\Foundry\Models\Notification::where('type', 'admin:hold-release')->exists());
    $this->assertTrue(\Foundry\Models\Notification::where('type', 'admin:import-completed')->exists());
});

it('parses metadata from blade comments correctly', function () {
    $seeder = new \Database\Seeders\NotificationSeeder;
    $seeder->run();

    $notification = \Foundry\Models\Notification::where('type', 'user:signup')->first();

    $this->assertNotNull($notification);
    $this->assertEquals('Signup', $notification->label);
    $this->assertEquals('Welcome to {{$app->name}} - Your Subscription Details', $notification->subject);
    $this->assertTrue($notification->is_default);
    $this->assertNotEmpty($notification->content);
});

it('parses import completed notification', function () {
    $seeder = new \Database\Seeders\NotificationSeeder;
    $seeder->run();

    $notification = \Foundry\Models\Notification::where('type', 'admin:import-completed')->first();

    $this->assertNotNull($notification);
    $this->assertEquals('Import Completed', $notification->label);
    $this->assertStringContainsString('[{{ $app->name }}] {{ $import->model }} import completed', $notification->subject);

    $normalizedContent = preg_replace('/\s+/', ' ', $notification->content);
    $this->assertStringContainsString('{{ $import->successed }}', $normalizedContent);
    $this->assertStringContainsString('{{ $import->failed }}', $normalizedContent);
    $this->assertStringContainsString('{{ $import->skipped }}', $normalizedContent);
});

it('updates existing notifications without duplicates', function () {
    \Foundry\Models\Notification::create([
        'label' => 'Old Label',
        'subject' => 'Old Subject',
        'type' => 'user:signup',
        'is_default' => false,
        'content' => 'Old content',
    ]);

    $this->assertEquals(1, \Foundry\Models\Notification::count());

    $seeder = new \Database\Seeders\NotificationSeeder;
    $seeder->run();

    $notification = \Foundry\Models\Notification::where('type', 'user:signup')->first();
    $this->assertEquals('Signup', $notification->label);
    $this->assertNotEquals('Old Label', $notification->label);

    $totalNotifications = \Foundry\Models\Notification::count();
    $this->assertGreaterThan(1, $totalNotifications);

    $uniqueTypes = \Foundry\Models\Notification::pluck('type')->unique()->count();
    $this->assertEquals($totalNotifications, $uniqueTypes);
});

it('seeds all notification types', function () {
    $seeder = new \Database\Seeders\NotificationSeeder;
    $seeder->run();

    $expectedTypes = [
        'user:invoice-sent', 'user:signup', 'user:subscription-cancel',
        'user:subscription-canceled', 'user:subscription-downgrade',
        'user:subscription-expired', 'user:subscription-renewed',
        'user:subscription-upgraded', 'user:support-ticket-notification',
        'user:support-ticket-confirmation', 'user:support-ticket-reply-notification',
        'user:payment-success', 'user:payment-failed', 'user:partial-refund',
        'common:user-login', 'common:password-reset-request',
        'admin:support-ticket-reply-notification', 'admin:subscription-expired',
        'admin:subscription-cancel', 'admin:hold-release',
        'admin:support-ticket-notification', 'admin:new-account',
        'admin:contact-us-notification', 'admin:import-completed',
        'admin:payment-failed', 'admin:refund-processed',
    ];

    $seededTypes = \Foundry\Models\Notification::pluck('type')->toArray();

    foreach ($expectedTypes as $type) {
        $this->assertContains($type, $seededTypes);
    }

    $this->assertCount(count($expectedTypes), $seededTypes);
});

it('handles missing optional metadata fields', function () {
    $seeder = new \Database\Seeders\NotificationSeeder;
    $seeder->run();

    $notifications = \Foundry\Models\Notification::all();

    foreach ($notifications as $notification) {
        $this->assertNotEmpty($notification->type);
        $this->assertNotEmpty($notification->label);
        $this->assertNotNull($notification->is_default);
    }
});
