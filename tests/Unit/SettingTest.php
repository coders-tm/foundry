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

    $this->assertEquals('Test App', Settings::get('config.name'));
    $this->assertEquals('test@example.com', Settings::get('config.email'));
});

it('can update app setting', function () {
    Settings::set('config', [
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    Settings::set('config.name', 'Updated Name');

    $this->assertEquals('Updated Name', Settings::get('config.name'));
    $this->assertEquals('original@example.com', Settings::get('config.email'));
});

it('maps email to multiple config keys', function () {
    Settings::set('config', [
        'email' => 'admin@example.com',
    ]);

    Settings::syncConfig();

    $this->assertEquals('admin@example.com', config('foundry.admin_email'));
    $this->assertEquals('admin@example.com', config('mail.from.address'));
});

it('maps name to mail from name', function () {
    Settings::set('config', [
        'name' => 'Test Application',
    ]);

    Settings::syncConfig();

    $this->assertEquals('Test Application', config('mail.from.name'));
});

it('maps currency to app config', function () {
    Settings::set('config', [
        'currency' => 'EUR',
    ]);

    Settings::syncConfig();

    $this->assertEquals('EUR', config('app.currency'));
});

it('maps timezone to app config', function () {
    Settings::set('config', [
        'timezone' => 'America/New_York',
    ]);

    Settings::syncConfig();

    $this->assertEquals('America/New_York', config('app.timezone'));
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

    $this->assertEquals('127.0.0.1', config('mail.mailers.smtp.host'));
    $this->assertEquals('ses-key', config('mail.mailers.ses.key'));
});

it('handles deeply nested dotted keys', function () {
    Settings::set('config.subscription.billing', [
        'interval' => 'month',
        'trial_days' => 14,
    ]);

    $this->assertEquals('month', Settings::get('config.subscription.billing.interval'));
    $this->assertEquals(14, Settings::get('config.subscription.billing.trial_days'));
});

it('merges nested values correctly in facade', function () {
    Settings::set('config.features', [
        'analytics' => true,
    ]);

    Settings::set('config.features.reports', true);

    $this->assertTrue(Settings::get('config.features.analytics'));
    $this->assertTrue(Settings::get('config.features.reports'));
});

it('works with settings helper function', function () {
    Settings::set('app', [
        'name' => 'Test App',
    ]);

    $this->assertEquals('Test App', settings('app.name'));

    settings(['app.name' => 'Helper Updated']);
    $this->assertEquals('Helper Updated', Settings::get('app.name'));
});

it('handles array values at any depth', function () {
    Settings::set('permissions.roles', [
        'admin' => ['create', 'read', 'update', 'delete'],
    ]);

    $adminPerms = Settings::get('permissions.roles.admin');
    $this->assertIsArray($adminPerms);
    $this->assertEquals(['create', 'read', 'update', 'delete'], $adminPerms);
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
