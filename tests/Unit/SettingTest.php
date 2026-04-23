<?php

namespace Foundry\Tests\Unit;

use Foundry\Models\Setting;
use Foundry\Tests\BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class SettingTest extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Setting::clearCache();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        Setting::clearCache();

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_app_setting()
    {
        $options = Setting::create('config', [
            'name' => 'Test App',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test App', $options['name']);
        $this->assertEquals('test@example.com', $options['email']);

        $this->assertDatabaseHas('settings', [
            'key' => 'config',
        ]);
    }

    #[Test]
    public function it_can_update_app_setting()
    {
        Setting::create('config', [
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $options = Setting::updateValue('config', [
            'name' => 'Updated Name',
        ]);

        $this->assertEquals('Updated Name', $options['name']);
        $this->assertEquals('original@example.com', $options['email']);
    }

    #[Test]
    public function it_can_replace_app_setting()
    {
        Setting::create('config', [
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $options = Setting::updateValue('config', [
            'name' => 'Replaced Name',
        ], true);

        $this->assertEquals('Replaced Name', $options['name']);
        $this->assertArrayNotHasKey('email', $options);
    }

    #[Test]
    public function it_maps_email_to_multiple_config_keys()
    {
        Setting::create('config', [
            'email' => 'admin@example.com',
        ]);

        Setting::syncConfig();

        // Should map to both foundry.admin_email and mail.from.address
        $this->assertEquals('admin@example.com', config('foundry.admin_email'));
        $this->assertEquals('admin@example.com', config('mail.from.address'));
    }

    #[Test]
    public function it_maps_name_to_mail_from_name()
    {
        Setting::create('config', [
            'name' => 'Test Application',
        ]);

        Setting::syncConfig();

        $this->assertEquals('Test Application', config('mail.from.name'));
    }

    #[Test]
    public function it_maps_currency_to_stripe_currency()
    {
        Setting::create('config', [
            'currency' => 'EUR',
        ]);

        Setting::syncConfig();

        $this->assertEquals('EUR', config('stripe.currency'));
    }

    #[Test]
    public function it_maps_currency_to_app_currency()
    {
        Setting::create('config', [
            'currency' => 'EUR',
        ]);

        Setting::syncConfig();

        $this->assertEquals('EUR', config('app.currency'));
    }

    #[Test]
    public function it_maps_timezone_to_app_config()
    {
        Setting::create('config', [
            'timezone' => 'America/New_York',
        ]);

        Setting::syncConfig();

        // Timezone is stored in config('app.timezone') for display conversion;
        // PHP's default timezone (used for DB operations) is intentionally kept as UTC.
        $this->assertEquals('America/New_York', config('app.timezone'));
    }

    #[Test]
    public function it_maps_subscription_config()
    {
        Setting::create('config', [
            'subscription' => [
                'anchor_from_invoice' => false,
                'downgrade_timing' => 'immediate',
            ],
        ]);

        Setting::syncConfig();

        // The mapping applies subscription as a whole to 'foundry.subscription'
        $this->assertIsArray(config('foundry.subscription'));
        $this->assertArrayHasKey('anchor_from_invoice', config('foundry.subscription'));
        $this->assertArrayHasKey('downgrade_timing', config('foundry.subscription'));
    }

    #[Test]
    public function it_maps_checkout_config()
    {
        Setting::create('config', [
            'checkout' => [
                'abandoned_cart_hours' => 4,
            ],
        ]);

        Setting::syncConfig();

        // The mapping applies checkout as a whole to 'foundry.shop'
        $this->assertIsArray(config('foundry.shop'));
        $this->assertArrayHasKey('abandoned_cart_hours', config('foundry.shop'));
    }

    #[Test]
    public function it_applies_config_overrides_immediately_on_update()
    {
        Setting::updateValue('config', [
            'email' => 'immediate@example.com',
        ]);

        // Should apply immediately without needing syncConfig()
        $this->assertEquals('immediate@example.com', config('foundry.admin_email'));
        $this->assertEquals('immediate@example.com', config('mail.from.address'));
    }

    #[Test]
    public function it_updates_cache_efficiently_on_update()
    {
        // Create initial setting
        Setting::create('config', [
            'name' => 'Initial Name',
        ]);

        // Get settings to populate cache
        $settings = Setting::getSettings();
        $this->assertArrayHasKey('config', $settings);

        // Update setting
        Setting::updateValue('config', [
            'name' => 'Updated Name',
        ]);

        // Cache should be updated
        $cachedSettings = Cache::get('foundry_settings');
        $this->assertEquals('Updated Name', $cachedSettings['config']['name']);
    }

    #[Test]
    public function it_can_find_setting_by_key()
    {
        Setting::create('config', [
            'name' => 'Test App',
            'email' => 'test@example.com',
        ]);

        $result = Setting::findByKey('config');

        $this->assertIsArray($result);
        $this->assertEquals('Test App', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
    }

    #[Test]
    public function it_returns_empty_array_for_non_existent_key()
    {
        $result = Setting::findByKey('non-existent');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_can_get_setting_with_dot_notation()
    {
        Setting::create('config', [
            'name' => 'Test App',
            'email' => 'test@example.com',
        ]);

        $name = Setting::get('config.name');
        $email = Setting::get('config.email');

        $this->assertEquals('Test App', $name);
        $this->assertEquals('test@example.com', $email);
    }

    #[Test]
    public function it_returns_default_value_for_non_existent_dot_notation()
    {
        $result = Setting::get('config.non-existent', 'default');

        $this->assertEquals('default', $result);
    }

    #[Test]
    public function it_can_get_entire_setting_without_dot_notation()
    {
        Setting::create('config', [
            'name' => 'Test App',
            'email' => 'test@example.com',
        ]);

        $result = Setting::get('config');

        $this->assertIsArray($result);
        $this->assertEquals('Test App', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
    }

    #[Test]
    public function it_can_get_specific_attribute_value()
    {
        Setting::create('config', [
            'name' => 'Test App',
            'email' => 'test@example.com',
        ]);

        $name = Setting::value('config', 'name');
        $email = Setting::value('config', 'email');
        $nonExistent = Setting::value('config', 'non-existent', 'default');

        $this->assertEquals('Test App', $name);
        $this->assertEquals('test@example.com', $email);
        $this->assertEquals('default', $nonExistent);
    }

    #[Test]
    public function it_caches_settings_for_performance()
    {
        Setting::create('config', [
            'name' => 'Test App',
        ]);

        // First call should hit database
        $settings1 = Setting::getSettings();

        // Second call should hit cache
        $settings2 = Setting::getSettings();

        $this->assertEquals($settings1, $settings2);
        $this->assertTrue(Cache::has('foundry_settings'));
    }

    #[Test]
    public function it_clears_cache_properly()
    {
        Setting::create('config', [
            'name' => 'Test App',
        ]);

        // Populate cache
        Setting::getSettings();
        $this->assertTrue(Cache::has('foundry_settings'));

        // Clear cache
        Setting::clearCache();
        $this->assertFalse(Cache::has('foundry_settings'));
    }

    #[Test]
    public function it_handles_nested_config_overrides()
    {
        Setting::create('config', [
            'subscription' => [
                'anchor_from_invoice' => false,
                'downgrade_timing' => 'immediate',
            ],
            'checkout' => [
                'abandoned_cart_hours' => 6,
            ],
        ]);

        Setting::syncConfig();

        // Check that nested configs are mapped to their respective config keys
        $subscriptionConfig = config('foundry.subscription');
        $shopConfig = config('foundry.shop');

        $this->assertIsArray($subscriptionConfig);
        $this->assertIsArray($shopConfig);
        $this->assertArrayHasKey('anchor_from_invoice', $subscriptionConfig);
        $this->assertArrayHasKey('downgrade_timing', $subscriptionConfig);
        $this->assertArrayHasKey('abandoned_cart_hours', $shopConfig);
    }

    #[Test]
    public function it_applies_all_config_mappings_correctly()
    {
        Setting::create('config', [
            'email' => 'multi@example.com',
            'name' => 'Multi Test App',
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'subscription' => [
                'anchor_from_invoice' => true,
                'downgrade_timing' => 'next_renewal',
            ],
            'checkout' => [
                'abandoned_cart_hours' => 3,
            ],
        ]);

        Setting::syncConfig();

        // Verify all mappings
        $this->assertEquals('multi@example.com', config('foundry.admin_email'));
        $this->assertEquals('multi@example.com', config('mail.from.address'));
        $this->assertEquals('Multi Test App', config('mail.from.name'));
        $this->assertEquals('GBP', config('stripe.currency'));
        $this->assertEquals('GBP', config('app.currency'));
        $this->assertEquals('Europe/London', config('app.timezone'));

        // Verify nested configs are applied
        $this->assertIsArray(config('foundry.subscription'));
        $this->assertIsArray(config('foundry.shop'));
    }

    #[Test]
    public function it_filters_empty_values_on_update()
    {
        $options = Setting::updateValue('config', [
            'name' => 'Test App',
            'email' => '',        // Empty string should be kept
            'currency' => null,   // Null should be kept
            'enabled' => false,   // False should be kept
            'count' => 0,         // Zero should be kept
            'items' => [],        // Empty array should be filtered out
        ]);

        // All scalar values should be preserved, even falsy ones
        $this->assertEquals('Test App', $options['name']);
        $this->assertEquals('', $options['email']);
        $this->assertNull($options['currency']);
        $this->assertFalse($options['enabled']);
        $this->assertEquals(0, $options['count']);

        // Empty arrays should be filtered out
        $this->assertArrayNotHasKey('items', $options);
    }

    #[Test]
    public function it_returns_collection_with_find_by_key_as_collection()
    {
        Setting::create('config', [
            'name' => 'Test App',
            'email' => 'test@example.com',
        ]);

        $result = Setting::findByKeyAsCollection('config');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals('Test App', $result->get('name'));
        $this->assertEquals('test@example.com', $result->get('email'));
    }

    #[Test]
    public function it_handles_multiple_settings_keys()
    {
        Setting::create('config', [
            'name' => 'App Config',
        ]);

        Setting::create('payment', [
            'gateway' => 'stripe',
        ]);

        $configSettings = Setting::findByKey('config');
        $paymentSettings = Setting::findByKey('payment');

        $this->assertEquals('App Config', $configSettings['name']);
        $this->assertEquals('stripe', $paymentSettings['gateway']);
    }

    #[Test]
    public function it_uses_alias_for_config_mapping()
    {
        // The 'config' key should use 'app' alias based on settings_override
        Setting::create('config', [
            'custom_property' => 'custom_value',
        ]);

        Setting::syncConfig();

        // Should be set under 'app' alias
        $this->assertEquals('custom_value', config('app.custom_property'));
    }

    #[Test]
    public function it_handles_dotted_keys_in_create()
    {
        // Creating with dotted key should use first segment as database key
        $options = Setting::create('config.app', [
            'name' => 'Test App',
            'url' => 'https://example.com',
        ]);

        // Should store under 'config' key with nested 'app' property
        $this->assertDatabaseHas('settings', [
            'key' => 'config',
        ]);

        // Verify nested structure
        $settings = Setting::findByKey('config');
        $this->assertArrayHasKey('app', $settings);
        $this->assertEquals('Test App', $settings['app']['name']);
        $this->assertEquals('https://example.com', $settings['app']['url']);
    }

    #[Test]
    public function it_handles_dotted_keys_in_update()
    {
        // Create initial setting
        Setting::create('config', [
            'email' => 'admin@example.com',
        ]);

        // Update using dotted key
        Setting::updateValue('config.app', [
            'name' => 'My App',
        ]);

        // Should preserve existing data and add nested data
        $settings = Setting::findByKey('config');
        $this->assertEquals('admin@example.com', $settings['email']);
        $this->assertArrayHasKey('app', $settings);
        $this->assertEquals('My App', $settings['app']['name']);
    }

    #[Test]
    public function it_handles_deeply_nested_dotted_keys()
    {
        // Create with deeply nested key
        Setting::create('config.subscription.billing', [
            'interval' => 'month',
            'trial_days' => 14,
        ]);

        // Verify deep nesting
        $settings = Setting::findByKey('config');
        $this->assertArrayHasKey('subscription', $settings);
        $this->assertArrayHasKey('billing', $settings['subscription']);
        $this->assertEquals('month', $settings['subscription']['billing']['interval']);
        $this->assertEquals(14, $settings['subscription']['billing']['trial_days']);
    }

    #[Test]
    public function it_merges_nested_values_correctly()
    {
        // Create initial nested value
        Setting::create('config.features', [
            'analytics' => true,
        ]);

        // Add another property to the same nested level
        Setting::updateValue('config.features', [
            'reports' => true,
        ]);

        // Should merge both properties
        $settings = Setting::findByKey('config');
        $this->assertTrue($settings['features']['analytics']);
        $this->assertTrue($settings['features']['reports']);
    }

    #[Test]
    public function it_handles_dotted_get_with_nested_data()
    {
        // Create nested data
        Setting::create('config', [
            'app' => [
                'name' => 'Test App',
                'version' => '1.0.0',
            ],
        ]);

        // Get with dot notation
        $name = Setting::get('config.app.name');
        $version = Setting::get('config.app.version');

        $this->assertEquals('Test App', $name);
        $this->assertEquals('1.0.0', $version);
    }

    #[Test]
    public function it_prevents_creating_duplicate_keys_with_dots()
    {
        // Create with dotted key
        Setting::create('config.app', [
            'name' => 'First',
        ]);

        // Update same dotted key
        Setting::updateValue('config.app', [
            'name' => 'Second',
        ]);

        // Should only have one database record with key 'config'
        $this->assertDatabaseCount('settings', 1);

        $settings = Setting::findByKey('config');
        $this->assertEquals('Second', $settings['app']['name']);
    }

    #[Test]
    public function it_works_like_laravel_config_for_nested_values()
    {
        // Create complex nested structure
        Setting::create('app', [
            'name' => 'Laravel App',
            'env' => 'production',
            'debug' => false,
            'database' => [
                'default' => 'mysql',
                'connections' => [
                    'mysql' => [
                        'host' => '127.0.0.1',
                        'port' => 3306,
                        'database' => 'myapp',
                        'username' => 'root',
                    ],
                    'redis' => [
                        'host' => 'localhost',
                        'port' => 6379,
                    ],
                ],
            ],
            'services' => [
                'stripe' => [
                    'key' => 'pk_test_123',
                    'secret' => 'sk_test_456',
                ],
            ],
        ]);

        // Test like Laravel config() - single level
        $this->assertEquals('Laravel App', Setting::get('app.name'));
        $this->assertEquals('production', Setting::get('app.env'));
        $this->assertFalse(Setting::get('app.debug'));

        // Test like Laravel config() - two levels
        $this->assertEquals('mysql', Setting::get('app.database.default'));

        // Test like Laravel config() - three levels
        $this->assertEquals('127.0.0.1', Setting::get('app.database.connections.mysql.host'));
        $this->assertEquals(3306, Setting::get('app.database.connections.mysql.port'));
        $this->assertEquals('myapp', Setting::get('app.database.connections.mysql.database'));

        // Test like Laravel config() - four levels deep
        $this->assertEquals('localhost', Setting::get('app.database.connections.redis.host'));
        $this->assertEquals(6379, Setting::get('app.database.connections.redis.port'));

        // Test like Laravel config() - services
        $this->assertEquals('pk_test_123', Setting::get('app.services.stripe.key'));
        $this->assertEquals('sk_test_456', Setting::get('app.services.stripe.secret'));

        // Test like Laravel config() - get entire nested array
        $database = Setting::get('app.database');
        $this->assertIsArray($database);
        $this->assertEquals('mysql', $database['default']);
        $this->assertArrayHasKey('connections', $database);

        // Test like Laravel config() - non-existent key returns default
        $this->assertNull(Setting::get('app.nonexistent'));
        $this->assertEquals('default', Setting::get('app.nonexistent', 'default'));
        $this->assertEquals('fallback', Setting::get('app.database.connections.postgres.host', 'fallback'));
    }

    #[Test]
    public function it_returns_entire_setting_when_no_dots_provided()
    {
        Setting::create('mail', [
            'from' => [
                'address' => 'hello@example.com',
                'name' => 'Example',
            ],
            'driver' => 'smtp',
        ]);

        // Get entire setting like config('mail')
        $mail = Setting::get('mail');

        $this->assertIsArray($mail);
        $this->assertEquals('smtp', $mail['driver']);
        $this->assertEquals('hello@example.com', $mail['from']['address']);
    }

    #[Test]
    public function it_handles_null_and_false_values_correctly()
    {
        Setting::create('flags', [
            'feature_enabled' => false,
            'nullable_value' => null,
            'zero_value' => 0,
            'empty_string' => '',
            'nested' => [
                'disabled' => false,
                'optional' => null,
            ],
        ]);

        // Should return actual false, not default
        $this->assertFalse(Setting::get('flags.feature_enabled'));
        $this->assertFalse(Setting::get('flags.feature_enabled', true));

        // Should return null when value is explicitly null
        $this->assertNull(Setting::get('flags.nullable_value'));

        // Should return 0, not default
        $this->assertEquals(0, Setting::get('flags.zero_value'));
        $this->assertEquals(0, Setting::get('flags.zero_value', 100));

        // Should return empty string, not default
        $this->assertEquals('', Setting::get('flags.empty_string'));
        $this->assertEquals('', Setting::get('flags.empty_string', 'default'));

        // Nested false/null values
        $this->assertFalse(Setting::get('flags.nested.disabled'));
        $this->assertNull(Setting::get('flags.nested.optional'));
    }

    #[Test]
    public function it_works_with_settings_helper_function()
    {
        Setting::create('app', [
            'name' => 'Test App',
            'config' => [
                'timezone' => 'UTC',
                'locale' => 'en',
            ],
        ]);

        // Test settings() helper works like config()
        $this->assertEquals('Test App', settings('app.name'));
        $this->assertEquals('UTC', settings('app.config.timezone'));
        $this->assertEquals('en', settings('app.config.locale'));
        $this->assertEquals('default', settings('app.nonexistent', 'default'));

        // Test getting all settings
        $all = settings();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('app', $all);
    }

    #[Test]
    public function it_handles_array_values_at_any_depth()
    {
        Setting::create('permissions', [
            'roles' => [
                'admin' => ['create', 'read', 'update', 'delete'],
                'editor' => ['create', 'read', 'update'],
                'viewer' => ['read'],
            ],
            'features' => [
                'api' => [
                    'endpoints' => ['users', 'posts', 'comments'],
                    'rate_limits' => [
                        'authenticated' => 1000,
                        'guest' => 100,
                    ],
                ],
            ],
        ]);

        // Get array values
        $adminPerms = Setting::get('permissions.roles.admin');
        $this->assertIsArray($adminPerms);
        $this->assertEquals(['create', 'read', 'update', 'delete'], $adminPerms);

        $endpoints = Setting::get('permissions.features.api.endpoints');
        $this->assertIsArray($endpoints);
        $this->assertEquals(['users', 'posts', 'comments'], $endpoints);

        $rateLimits = Setting::get('permissions.features.api.rate_limits');
        $this->assertIsArray($rateLimits);
        $this->assertEquals(1000, $rateLimits['authenticated']);
        $this->assertEquals(100, $rateLimits['guest']);
    }

    #[Test]
    public function it_matches_config_behavior_for_edge_cases()
    {
        Setting::create('edge', [
            'numeric_key' => [
                0 => 'zero',
                1 => 'one',
                2 => 'two',
            ],
            'mixed' => [
                'string' => 'value',
                'number' => 42,
                'bool' => true,
                'array' => ['a', 'b', 'c'],
            ],
        ]);

        // Numeric array keys
        $this->assertEquals('zero', Setting::get('edge.numeric_key.0'));
        $this->assertEquals('one', Setting::get('edge.numeric_key.1'));

        // Mixed types
        $this->assertEquals('value', Setting::get('edge.mixed.string'));
        $this->assertEquals(42, Setting::get('edge.mixed.number'));
        $this->assertTrue(Setting::get('edge.mixed.bool'));
        $this->assertEquals(['a', 'b', 'c'], Setting::get('edge.mixed.array'));
    }
}
