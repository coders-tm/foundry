<?php

use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->renderer = new NotificationTemplateRenderer;
});

it('renders default app shortcodes', function () {
    config(['app.name' => 'Test App']);
    config(['app.url' => 'https://test.com']);
    config(['foundry.domain' => 'test.com']);
    config(['foundry.admin_email' => 'admin@test.com']);

    $template = 'Welcome to {{APP_NAME}} at {{APP_URL}}. Contact: {{SUPPORT_EMAIL}}';

    $result = $this->renderer->render($template, []);

    $this->assertStringContainsString('Welcome to Test App', $result);
    $this->assertStringContainsString('at https://test.com', $result);
    $this->assertStringContainsString('Contact: admin@test.com', $result);
});

it('renders all default shortcodes', function () {
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
});

it('merges custom app shortcodes', function () {
    Foundry\Foundry::$appShortCodes = [
        'company' => [
            'name' => 'Acme Corp',
            'phone' => '+1-555-0123',
        ],
    ];

    $template = 'Company: {{COMPANY_NAME}}, Phone: {{COMPANY_PHONE}}';

    $result = $this->renderer->render($template, []);

    $this->assertStringContainsString('Company: Acme Corp', $result);
    $this->assertStringContainsString('Phone: +1-555-0123', $result);

    Foundry\Foundry::$appShortCodes = [];
});

it('custom app shortcodes can override defaults', function () {
    config(['app.name' => 'Default App']);

    Foundry\Foundry::$appShortCodes = [
        'app' => [
            'name' => 'Override App',
        ],
    ];

    $template = 'Legacy: {{APP_NAME}}';

    $result = $this->renderer->render($template, []);

    $this->assertStringContainsString('Legacy: Override App', $result);

    Foundry\Foundry::$appShortCodes = [];
});

it('custom app shortcodes can add new nested data', function () {
    Foundry\Foundry::$appShortCodes = [
        'company' => [
            'name' => 'Acme Corporation',
            'phone' => '+1-555-0123',
        ],
    ];

    $template = 'Contact {{COMPANY_NAME}} at {{COMPANY_PHONE}}';

    $result = $this->renderer->render($template, []);

    $this->assertStringContainsString('Contact Acme Corporation', $result);
    $this->assertStringContainsString('at +1-555-0123', $result);

    Foundry\Foundry::$appShortCodes = [];
});

it('default shortcodes used as fallback when not in user data', function () {
    config(['app.name' => 'Default App Name']);
    config(['foundry.domain' => 'default.com']);

    $template = 'App: {{APP_NAME}}, Domain: {{APP_DOMAIN}}, Custom: {{CUSTOM_VALUE}}';

    $result = $this->renderer->render($template, [
        'custom_value' => 'User Value',
    ]);

    $this->assertStringContainsString('App: Default App Name', $result);
    $this->assertStringContainsString('Domain: default.com', $result);
    $this->assertStringContainsString('Custom: User Value', $result);
});

it('default shortcodes work with blade directives', function () {
    config(['app.name' => 'Test Application']);

    $template = <<<'BLADE'
@if(true)
Welcome to {{APP_NAME}}!
@endif
BLADE;

    $result = $this->renderer->render($template, []);

    $this->assertStringContainsString('Welcome to Test Application!', $result);
});

it('billing page shortcode renders correctly', function () {
    $template = 'Visit your billing page: {{BILLING_PAGE}}';

    $result = $this->renderer->render($template, []);

    $this->assertStringContainsString('Visit your billing page:', $result);
    $this->assertStringContainsString('billing', $result);
});

it('custom app shortcodes support both scalar and nested', function () {
    Foundry\Foundry::$appShortCodes = [
        'company' => [
            'name' => 'Acme Corporation',
            'address' => '123 Main St',
        ],
        'tagline' => 'Excellence in Everything',
    ];

    $template = <<<'BLADE'
{{COMPANY_NAME}} - {{COMPANY_ADDRESS}}
Tagline: {{TAGLINE}}
BLADE;

    $result = $this->renderer->render($template, []);

    $this->assertStringContainsString('Acme Corporation', $result);
    $this->assertStringContainsString('123 Main St', $result);
    $this->assertStringContainsString('Tagline: Excellence in Everything', $result);

    Foundry\Foundry::$appShortCodes = [];
});
