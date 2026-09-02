<?php

use Foundry\Events\SettingChanged;
use Foundry\Facades\Settings;
use Foundry\Tests\BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

uses(BaseTestCase::class);

beforeEach(function () {
    $this->testSettingsPath = base_path('tests/settings_test.json');
    Settings::setPath($this->testSettingsPath);

    if (file_exists($this->testSettingsPath)) {
        unlink($this->testSettingsPath);
    }

    Config::set('settings', []);
});

afterEach(function () {
    if (file_exists($this->testSettingsPath)) {
        unlink($this->testSettingsPath);
    }
});

it('can set and get app setting', function () {
    Settings::set('config', [
        'name' => 'Test App',
        'email' => 'test@example.com',
    ]);

    expect(Settings::get('config.name'))->toBe('Test App');
    expect(Settings::get('config.email'))->toBe('test@example.com');
});

it('can update app setting', function () {
    Settings::set('config', [
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    Settings::set('config.name', 'Updated Name');

    expect(Settings::get('config.name'))->toBe('Updated Name');
    expect(Settings::get('config.email'))->toBe('original@example.com');
});

it('maps email to multiple config keys', function () {
    Settings::set('config', [
        'email' => 'admin@example.com',
    ]);

    Settings::syncConfig();

    expect(config('foundry.admin_email'))->toBe('admin@example.com');
    expect(config('mail.from.address'))->toBe('admin@example.com');
});

it('maps name to mail from name', function () {
    Settings::set('config', [
        'name' => 'Test Application',
    ]);

    Settings::syncConfig();

    expect(config('mail.from.name'))->toBe('Test Application');
});

it('maps currency to app config', function () {
    Settings::set('config', [
        'currency' => 'EUR',
    ]);

    Settings::syncConfig();

    expect(config('app.currency'))->toBe('EUR');
});

it('maps timezone to app config', function () {
    Settings::set('config', [
        'timezone' => 'America/New_York',
    ]);

    Settings::syncConfig();

    expect(config('app.timezone'))->toBe('America/New_York');
});

it('handles nested config overrides without replacing entire keys', function () {
    Config::set('mail.mailers.ses', ['key' => 'ses-key']);

    Settings::set('mail', [
        'default' => 'smtp',
        'mailers' => [
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => '1025',
            ],
        ],
    ]);

    Settings::syncConfig();

    expect(config('mail.mailers.smtp.host'))->toBe('127.0.0.1');
    expect(config('mail.mailers.ses.key'))->toBe('ses-key');
});

it('handles deeply nested dotted keys', function () {
    Settings::set('config.subscription.billing', [
        'interval' => 'month',
        'trial_days' => 14,
    ]);

    expect(Settings::get('config.subscription.billing.interval'))->toBe('month');
    expect(Settings::get('config.subscription.billing.trial_days'))->toBe(14);
});

it('merges nested values correctly in facade', function () {
    Settings::set('config.features', [
        'analytics' => true,
    ]);

    Settings::set('config.features.reports', true);

    expect(Settings::get('config.features.analytics'))->toBeTrue();
    expect(Settings::get('config.features.reports'))->toBeTrue();
});

it('works with settings helper function', function () {
    Settings::set('app', [
        'name' => 'Test App',
    ]);

    expect(settings('app.name'))->toBe('Test App');

    settings(['app.name' => 'Helper Updated']);
    expect(Settings::get('app.name'))->toBe('Helper Updated');
});

it('handles array values at any depth', function () {
    Settings::set('permissions.roles', [
        'admin' => ['create', 'read', 'update', 'delete'],
    ]);

    $adminPerms = Settings::get('permissions.roles.admin');
    expect($adminPerms)->toBeArray();
    expect($adminPerms)->toBe(['create', 'read', 'update', 'delete']);
});

it('only fires event when value actually changes', function () {
    Event::fake();

    Settings::set('config.name', 'Initial Name');
    Event::assertDispatched(SettingChanged::class);

    Event::fake();

    Settings::set('config.name', 'Initial Name');
    Event::assertNotDispatched(SettingChanged::class);

    Settings::set('config.name', 'Different Name');
    Event::assertDispatched(SettingChanged::class);
});
