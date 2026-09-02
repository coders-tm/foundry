<?php

use Database\Seeders\NotificationSeeder;
use Foundry\Models\Notification;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class)
    ->use(RefreshDatabase::class);

beforeEach(function () {
    Notification::query()->delete();
});

it('loads notification templates from blade files', function () {
    $seeder = new NotificationSeeder;
    $seeder->run();

    expect(Notification::count())->toBeGreaterThan(0);
    expect(Notification::where('type', 'user:signup')->exists())->toBeTrue();
    expect(Notification::where('type', 'admin:hold-release')->exists())->toBeTrue();
    expect(Notification::where('type', 'admin:import-completed')->exists())->toBeTrue();
});

it('parses metadata from blade comments correctly', function () {
    $seeder = new NotificationSeeder;
    $seeder->run();

    $notification = Notification::where('type', 'user:signup')->first();

    expect($notification)->not->toBeNull();
    expect($notification->label)->toBe('Signup');
    expect($notification->subject)->toBe('Welcome to {{$app->name}} - Your Subscription Details');
    expect($notification->is_default)->toBeTrue();
    expect($notification->content)->not->toBeEmpty();
});

it('parses import completed notification', function () {
    $seeder = new NotificationSeeder;
    $seeder->run();

    $notification = Notification::where('type', 'admin:import-completed')->first();

    expect($notification)->not->toBeNull();
    expect($notification->label)->toBe('Import Completed');
    expect($notification->subject)->toContain('[{{ $app->name }}] {{ $import->model }} import completed');

    $normalizedContent = preg_replace('/\s+/', ' ', $notification->content);
    expect($normalizedContent)->toContain('{{ $import->successed }}');
    expect($normalizedContent)->toContain('{{ $import->failed }}');
    expect($normalizedContent)->toContain('{{ $import->skipped }}');
});

it('updates existing notifications without duplicates', function () {
    Notification::create([
        'label' => 'Old Label',
        'subject' => 'Old Subject',
        'type' => 'user:signup',
        'is_default' => false,
        'content' => 'Old content',
    ]);

    expect(Notification::count())->toEqual(1);

    $seeder = new NotificationSeeder;
    $seeder->run();

    $notification = Notification::where('type', 'user:signup')->first();
    expect($notification->label)->toBe('Signup');
    expect($notification->label)->not->toBe('Old Label');

    $totalNotifications = Notification::count();
    expect($totalNotifications)->toBeGreaterThan(1);

    $uniqueTypes = Notification::pluck('type')->unique()->count();
    expect($totalNotifications)->toEqual($uniqueTypes);
});

it('seeds all notification types', function () {
    $seeder = new NotificationSeeder;
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

    $seededTypes = Notification::pluck('type')->toArray();

    foreach ($expectedTypes as $type) {
        expect($seededTypes)->toContain($type);
    }

    expect($seededTypes)->toHaveCount(count($expectedTypes));
});

it('handles missing optional metadata fields', function () {
    $seeder = new NotificationSeeder;
    $seeder->run();

    $notifications = Notification::all();

    foreach ($notifications as $notification) {
        expect($notification->type)->not->toBeEmpty();
        expect($notification->label)->not->toBeEmpty();
        expect($notification->is_default)->not->toBeNull();
    }
});
