<?php

uses(Foundry\Tests\BaseTestCase::class);

beforeEach(function () {
    $this->testSettingsPath = base_path('tests/settings_test.json');
    \Foundry\Facades\Settings::setPath($this->testSettingsPath);

    if (file_exists($this->testSettingsPath)) {
        unlink($this->testSettingsPath);
    }

    \Illuminate\Support\Facades\Config::set('settings', []);
});

afterEach(function () {
    if (file_exists($this->testSettingsPath)) {
        unlink($this->testSettingsPath);
    }
});

it('can set and get app setting', function () {
    \Foundry\Facades\Settings::set('config', [
        'name' => 'Test App',
        'email' => 'test@example.com',
    ]);

    $this->assertEquals('Test App', \Foundry\Facades\Settings::get('config.name'));
    $this->assertEquals('test@example.com', \Foundry\Facades\Settings::get('config.email'));
});

it('can update app setting', function () {
    \Foundry\Facades\Settings::set('config', [
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    \Foundry\Facades\Settings::set('config.name', 'Updated Name');

    $this->assertEquals('Updated Name', \Foundry\Facades\Settings::get('config.name'));
    $this->assertEquals('original@example.com', \Foundry\Facades\Settings::get('config.email'));
});

it('maps email to multiple config keys', function () {
    \Foundry\Facades\Settings::set('config', [
        'email' => 'admin@example.com',
    ]);

    \Foundry\Facades\Settings::syncConfig();

    $this->assertEquals('admin@example.com', config('foundry.admin_email'));
    $this->assertEquals('admin@example.com', config('mail.from.address'));
});

it('maps name to mail from name', function () {
    \Foundry\Facades\Settings::set('config', [
        'name' => 'Test Application',
    ]);

    \Foundry\Facades\Settings::syncConfig();

    $this->assertEquals('Test Application', config('mail.from.name'));
});

it('maps currency to app config', function () {
    \Foundry\Facades\Settings::set('config', [
        'currency' => 'EUR',
    ]);

    \Foundry\Facades\Settings::syncConfig();

    $this->assertEquals('EUR', config('app.currency'));
});

it('maps timezone to app config', function () {
    \Foundry\Facades\Settings::set('config', [
        'timezone' => 'America/New_York',
    ]);

    \Foundry\Facades\Settings::syncConfig();

    $this->assertEquals('America/New_York', config('app.timezone'));
});

it('handles nested config overrides without replacing entire keys', function () {
    \Illuminate\Support\Facades\Config::set('mail.mailers.ses', ['key' => 'ses-key']);

    \Foundry\Facades\Settings::set('mail', [
        'default' => 'smtp',
        'mailers' => [
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => '1025',
            ],
        ],
    ]);

    \Foundry\Facades\Settings::syncConfig();

    $this->assertEquals('127.0.0.1', config('mail.mailers.smtp.host'));
    $this->assertEquals('ses-key', config('mail.mailers.ses.key'));
});

it('handles deeply nested dotted keys', function () {
    \Foundry\Facades\Settings::set('config.subscription.billing', [
        'interval' => 'month',
        'trial_days' => 14,
    ]);

    $this->assertEquals('month', \Foundry\Facades\Settings::get('config.subscription.billing.interval'));
    $this->assertEquals(14, \Foundry\Facades\Settings::get('config.subscription.billing.trial_days'));
});

it('merges nested values correctly in facade', function () {
    \Foundry\Facades\Settings::set('config.features', [
        'analytics' => true,
    ]);

    \Foundry\Facades\Settings::set('config.features.reports', true);

    $this->assertTrue(\Foundry\Facades\Settings::get('config.features.analytics'));
    $this->assertTrue(\Foundry\Facades\Settings::get('config.features.reports'));
});

it('works with settings helper function', function () {
    \Foundry\Facades\Settings::set('app', [
        'name' => 'Test App',
    ]);

    $this->assertEquals('Test App', settings('app.name'));

    settings(['app.name' => 'Helper Updated']);
    $this->assertEquals('Helper Updated', \Foundry\Facades\Settings::get('app.name'));
});

it('handles array values at any depth', function () {
    \Foundry\Facades\Settings::set('permissions.roles', [
        'admin' => ['create', 'read', 'update', 'delete'],
    ]);

    $adminPerms = \Foundry\Facades\Settings::get('permissions.roles.admin');
    $this->assertIsArray($adminPerms);
    $this->assertEquals(['create', 'read', 'update', 'delete'], $adminPerms);
});

it('only fires event when value actually changes', function () {
    \Illuminate\Support\Facades\Event::fake();

    \Foundry\Facades\Settings::set('config.name', 'Initial Name');
    \Illuminate\Support\Facades\Event::assertDispatched(\Foundry\Events\SettingChanged::class);

    \Illuminate\Support\Facades\Event::fake();

    \Foundry\Facades\Settings::set('config.name', 'Initial Name');
    \Illuminate\Support\Facades\Event::assertNotDispatched(\Foundry\Events\SettingChanged::class);

    \Foundry\Facades\Settings::set('config.name', 'Different Name');
    \Illuminate\Support\Facades\Event::assertDispatched(\Foundry\Events\SettingChanged::class);
});
