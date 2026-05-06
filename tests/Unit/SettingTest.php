<?php

namespace Foundry\Tests\Unit;

use Foundry\Events\SettingChanged;
use Foundry\Facades\Settings;
use Foundry\Tests\BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

class SettingTest extends BaseTestCase
{
    protected string $testSettingsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testSettingsPath = base_path('tests/settings_test.json');
        Settings::setPath($this->testSettingsPath);

        // Clear existing test file
        if (file_exists($this->testSettingsPath)) {
            unlink($this->testSettingsPath);
        }

        // Reset config to avoid interference between tests
        Config::set('settings', []);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testSettingsPath)) {
            unlink($this->testSettingsPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_set_and_get_app_setting()
    {
        Settings::set('config', [
            'name' => 'Test App',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test App', Settings::get('config.name'));
        $this->assertEquals('test@example.com', Settings::get('config.email'));
    }

    #[Test]
    public function it_can_update_app_setting()
    {
        Settings::set('config', [
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        Settings::set('config.name', 'Updated Name');

        $this->assertEquals('Updated Name', Settings::get('config.name'));
        $this->assertEquals('original@example.com', Settings::get('config.email'));
    }

    #[Test]
    public function it_maps_email_to_multiple_config_keys()
    {
        Settings::set('config', [
            'email' => 'admin@example.com',
        ]);

        Settings::syncConfig();

        // Should map to both foundry.admin_email and mail.from.address based on override map
        $this->assertEquals('admin@example.com', config('foundry.admin_email'));
        $this->assertEquals('admin@example.com', config('mail.from.address'));
    }

    #[Test]
    public function it_maps_name_to_mail_from_name()
    {
        Settings::set('config', [
            'name' => 'Test Application',
        ]);

        Settings::syncConfig();

        $this->assertEquals('Test Application', config('mail.from.name'));
    }

    #[Test]
    public function it_maps_currency_to_app_config()
    {
        Settings::set('config', [
            'currency' => 'EUR',
        ]);

        Settings::syncConfig();

        $this->assertEquals('EUR', config('stripe.currency'));
        $this->assertEquals('EUR', config('app.currency'));
    }

    #[Test]
    public function it_maps_timezone_to_app_config()
    {
        Settings::set('config', [
            'timezone' => 'America/New_York',
        ]);

        Settings::syncConfig();

        $this->assertEquals('America/New_York', config('app.timezone'));
    }

    #[Test]
    public function it_handles_nested_config_overrides_without_replacing_entire_keys()
    {
        // Mock existing mailers config
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

        // Check that SMTP host was set
        $this->assertEquals('127.0.0.1', config('mail.mailers.smtp.host'));

        // IMPORTANT: Check that existing SES config was NOT wiped out
        $this->assertEquals('ses-key', config('mail.mailers.ses.key'));
    }

    #[Test]
    public function it_handles_deeply_nested_dotted_keys()
    {
        Settings::set('config.subscription.billing', [
            'interval' => 'month',
            'trial_days' => 14,
        ]);

        $this->assertEquals('month', Settings::get('config.subscription.billing.interval'));
        $this->assertEquals(14, Settings::get('config.subscription.billing.trial_days'));
    }

    #[Test]
    public function it_merges_nested_values_correctly_in_facade()
    {
        Settings::set('config.features', [
            'analytics' => true,
        ]);

        Settings::set('config.features.reports', true);

        $this->assertTrue(Settings::get('config.features.analytics'));
        $this->assertTrue(Settings::get('config.features.reports'));
    }

    #[Test]
    public function it_works_with_settings_helper_function()
    {
        Settings::set('app', [
            'name' => 'Test App',
        ]);

        $this->assertEquals('Test App', settings('app.name'));

        // Test setting via helper
        settings(['app.name' => 'Helper Updated']);
        $this->assertEquals('Helper Updated', Settings::get('app.name'));
    }

    #[Test]
    public function it_handles_array_values_at_any_depth()
    {
        Settings::set('permissions.roles', [
            'admin' => ['create', 'read', 'update', 'delete'],
        ]);

        $adminPerms = Settings::get('permissions.roles.admin');
        $this->assertIsArray($adminPerms);
        $this->assertEquals(['create', 'read', 'update', 'delete'], $adminPerms);
    }

    #[Test]
    public function it_only_fires_event_when_value_actually_changes()
    {
        Event::fake();

        // Initial set - should fire event
        Settings::set('config.name', 'Initial Name');
        Event::assertDispatched(SettingChanged::class);

        // Reset fake to clear recorded events
        Event::fake();

        // Set to same value - should NOT fire event
        Settings::set('config.name', 'Initial Name');
        Event::assertNotDispatched(SettingChanged::class);

        // Set to different value - should fire event
        Settings::set('config.name', 'Different Name');
        Event::assertDispatched(SettingChanged::class);
    }
}
