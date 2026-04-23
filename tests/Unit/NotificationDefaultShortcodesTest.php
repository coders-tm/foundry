<?php

namespace Foundry\Tests\Unit;

use Foundry\Foundry;
use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Tests\TestCase; // Changed from BaseTestCase to TestCase

class NotificationDefaultShortcodesTest extends TestCase // Extended TestCase for full Laravel environment
{
    protected NotificationTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new NotificationTemplateRenderer;
    }

    public function test_it_renders_default_app_shortcodes()
    {
        config(['app.name' => 'Test App']);
        config(['app.url' => 'https://test.com']);
        config(['foundry.domain' => 'test.com']);
        config(['foundry.admin_email' => 'admin@test.com']);

        $template = 'Welcome to {{APP_NAME}} at {{APP_URL}}. Contact: {{SUPPORT_EMAIL}}';

        $result = $this->renderer->render($template, []);

        $this->assertStringContainsString('Welcome to Test App', $result);
        $this->assertStringContainsString('at https://test.com', $result);
        $this->assertStringContainsString('Contact: admin@test.com', $result);
    }

    public function test_it_renders_all_default_shortcodes()
    {
        config(['app.name' => 'MyApp']);
        config(['app.url' => 'https://myapp.com']);
        config(['foundry.domain' => 'myapp.com']);
        config(['foundry.admin_email' => 'support@myapp.com']);
        config(['foundry.admin_url' => 'https://myapp.com/admin']);

        $template = <<<'BLADE'
App: {{APP_NAME}}
Domain: {{APP_DOMAIN}}
URL: {{APP_URL}}
Support: {{SUPPORT_EMAIL}}
Member Page: {{MEMBER_PAGE}}
Admin Page: {{ADMIN_PAGE}}
BLADE;

        $result = $this->renderer->render($template, []);

        $this->assertStringContainsString('App: MyApp', $result);
        $this->assertStringContainsString('Domain: myapp.com', $result);
        $this->assertStringContainsString('URL: https://myapp.com', $result);
        $this->assertStringContainsString('Support: support@myapp.com', $result);
        $this->assertStringContainsString('Admin Page: https://myapp.com/admin', $result);
    }

    public function test_it_merges_custom_app_shortcodes()
    {
        // Custom app shortcodes should be structured data, not shortcode format
        Foundry::$appShortCodes = [
            'company' => [
                'name' => 'Acme Corp',
                'phone' => '+1-555-0123',
            ],
        ];

        // Test UPPERCASE formats only
        $template = 'Company: {{COMPANY_NAME}}, Phone: {{COMPANY_PHONE}}';

        $result = $this->renderer->render($template, []);

        $this->assertStringContainsString('Company: Acme Corp', $result);
        $this->assertStringContainsString('Phone: +1-555-0123', $result);

        // Clean up
        Foundry::$appShortCodes = [];
    }

    public function test_custom_app_shortcodes_can_override_defaults()
    {
        config(['app.name' => 'Default App']);

        // Custom shortcodes should override defaults using structured data
        Foundry::$appShortCodes = [
            'app' => [
                'name' => 'Override App',
            ],
        ];

        $template = 'Legacy: {{APP_NAME}}';

        $result = $this->renderer->render($template, []);

        // Should get the override value
        $this->assertStringContainsString('Legacy: Override App', $result);

        // Clean up
        Foundry::$appShortCodes = [];
    }

    public function test_custom_app_shortcodes_can_add_new_nested_data()
    {
        // Test that custom app shortcodes work with structured data
        Foundry::$appShortCodes = [
            'company' => [
                'name' => 'Acme Corporation',
                'phone' => '+1-555-0123',
            ],
        ];

        $template = 'Contact {{COMPANY_NAME}} at {{COMPANY_PHONE}}';

        $result = $this->renderer->render($template, []);

        $this->assertStringContainsString('Contact Acme Corporation', $result);
        $this->assertStringContainsString('at +1-555-0123', $result);

        // Clean up
        Foundry::$appShortCodes = [];
    }

    public function test_default_shortcodes_used_as_fallback_when_not_in_user_data()
    {
        config(['app.name' => 'Default App Name']);
        config(['foundry.domain' => 'default.com']);

        // Template uses both APP_NAME (which has default) and a user-provided value
        $template = 'App: {{APP_NAME}}, Domain: {{APP_DOMAIN}}, Custom: {{CUSTOM_VALUE}}';

        // Don't provide app in user data - should use config default
        // But do provide custom_value as scalar
        $result = $this->renderer->render($template, [
            'custom_value' => 'User Value',
        ]);

        $this->assertStringContainsString('App: Default App Name', $result);
        $this->assertStringContainsString('Domain: default.com', $result);
        $this->assertStringContainsString('Custom: User Value', $result);
    }

    public function test_default_shortcodes_work_with_blade_directives()
    {
        config(['app.name' => 'Test Application']);

        $template = <<<'BLADE'
@if(true)
Welcome to {{APP_NAME}}!
@endif
BLADE;

        $result = $this->renderer->render($template, []);

        $this->assertStringContainsString('Welcome to Test Application!', $result);
    }

    public function test_billing_page_shortcode_renders_correctly()
    {
        $template = 'Visit your billing page: {{BILLING_PAGE}}';

        $result = $this->renderer->render($template, []);

        $this->assertStringContainsString('Visit your billing page:', $result);
        $this->assertStringContainsString('billing', $result);
    }

    public function test_custom_app_shortcodes_support_both_scalar_and_nested()
    {
        // Test both scalar and nested data structures
        Foundry::$appShortCodes = [
            'company' => [
                'name' => 'Acme Corporation',
                'address' => '123 Main St',
            ],
            'tagline' => 'Excellence in Everything', // Scalar value
        ];

        $template = <<<'BLADE'
{{COMPANY_NAME}} - {{COMPANY_ADDRESS}}
Tagline: {{TAGLINE}}
BLADE;

        $result = $this->renderer->render($template, []);

        // Nested data creates UPPERCASE
        $this->assertStringContainsString('Acme Corporation', $result);
        $this->assertStringContainsString('123 Main St', $result);

        // Scalar data creates UPPERCASE
        $this->assertStringContainsString('Tagline: Excellence in Everything', $result);

        // Clean up
        Foundry::$appShortCodes = [];
    }
}
