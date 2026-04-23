<?php

namespace Foundry\Tests\Unit;

use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Tests\TestCase; // Changed from BaseTestCase to TestCase
use PHPUnit\Framework\Attributes\Test;

class NotificationTemplateRendererTest extends TestCase // Extended TestCase for full Laravel environment
{
    protected NotificationTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = app(NotificationTemplateRenderer::class);
    }

    #[Test]
    public function it_renders_legacy_uppercase_shortcodes_for_backward_compatibility()
    {
        $template = <<<'BLADE'
Hello {{USER_FIRST_NAME}},

Your subscription to {{PLAN_LABEL}} is active.

Plan Price: {{PLAN_PRICE}}

Thank you!
BLADE;

        $result = $this->renderer->render($template, [
            'user' => ['first_name' => 'John', 'email' => 'john@example.com'],
            'plan' => ['label' => 'Premium Plan', 'price' => '$99/month'],
        ]);

        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('Premium Plan', $result);
        $this->assertStringContainsString('$99/month', $result);
        $this->assertStringNotContainsString('{{USER_FIRST_NAME}}', $result);
        $this->assertStringNotContainsString('{{PLAN_LABEL}}', $result);
    }

    #[Test]
    public function it_renders_blade_variable_syntax()
    {
        $template = <<<'BLADE'
Hello {{ $user->first_name }},

Your subscription to {{ $plan->label }} is active.

@if($showDetails)
Plan Price: {{ $plan->price }}
@endif

Thank you!
BLADE;

        $result = $this->renderer->render($template, [
            'user' => ['first_name' => 'John', 'email' => 'john@example.com'],
            'plan' => ['label' => 'Premium Plan', 'price' => '$99/month'],
            'showDetails' => true,
        ]);

        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('Premium Plan', $result);
        $this->assertStringContainsString('$99/month', $result);
    }

    #[Test]
    public function it_supports_mixing_uppercase_and_blade_formats()
    {
        $template = <<<'BLADE'
Hello {{ $user->first_name }} {{USER_LAST_NAME}},

Your email is {{ $user->email }}.
Your subscription to {{PLAN_LABEL}} is active.

@if($showPrice)
Plan Price: {{ $plan->price }}
@endif

Thank you!
BLADE;

        $result = $this->renderer->render($template, [
            'user' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ],
            'plan' => ['label' => 'Premium Plan', 'price' => '$99/month'],
            'showPrice' => true,
        ]);

        $this->assertStringContainsString('Hello John Doe', $result);
        $this->assertStringContainsString('john@example.com', $result);
        $this->assertStringContainsString('Premium Plan', $result);
        $this->assertStringContainsString('$99/month', $result);
    }

    #[Test]
    public function it_renders_simple_scalar_values_in_uppercase_format()
    {
        $template = <<<'BLADE'
Custom Scalar: {{CUSTOM_VALUE}}
Domain: {{APP_DOMAIN}}
@if($isActive)
Status: Active
@endif
BLADE;

        $result = $this->renderer->render($template, [
            'custom_value' => 'Test Value',
            'app' => ['domain' => 'example.com'],
            'isActive' => true,
        ]);

        $this->assertStringContainsString('Custom Scalar: Test Value', $result);
        $this->assertStringContainsString('Domain: example.com', $result);
        $this->assertStringContainsString('Status: Active', $result);
    }

    #[Test]
    public function it_handles_model_objects_with_to_array_method()
    {
        // Create a mock object with toArray method
        $user = new class
        {
            public function toArray()
            {
                return [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'email' => 'jane@example.com',
                ];
            }
        };

        $template = <<<'BLADE'
Blade format: {{ $user->first_name }} {{ $user->email }}
Legacy format: {{USER_LAST_NAME}}
BLADE;

        $result = $this->renderer->render($template, [
            'user' => $user,
        ]);

        $this->assertStringContainsString('Blade format: Jane jane@example.com', $result);
        $this->assertStringContainsString('Legacy format: Smith', $result);
    }

    #[Test]
    public function it_handles_nested_array_data()
    {
        $template = <<<'BLADE'
User: {{ $user->first_name }} {{ $user->last_name }}
Plan: {{ $subscription->plan_name }}
Price: {{SUBSCRIPTION_PRICE}}

@if($user->is_premium)
Premium Member
@endif
BLADE;

        $result = $this->renderer->render($template, [
            'user' => [
                'first_name' => 'Alice',
                'last_name' => 'Johnson',
                'is_premium' => true,
            ],
            'subscription' => [
                'plan_name' => 'Enterprise',
                'price' => '$199/month',
            ],
        ]);

        $this->assertStringContainsString('User: Alice Johnson', $result);
        $this->assertStringContainsString('Plan: Enterprise', $result);
        $this->assertStringContainsString('Price: $199/month', $result);
        $this->assertStringContainsString('Premium Member', $result);
    }

    #[Test]
    public function it_handles_null_values_gracefully()
    {
        $template = <<<'BLADE'
Name: {{ $user->first_name }}
Middle: {{ $user->middle_name }}
Last: {{USER_LAST_NAME}}
BLADE;

        $result = $this->renderer->render($template, [
            'user' => [
                'first_name' => 'John',
                'middle_name' => null,
                'last_name' => 'Doe',
            ],
        ]);

        $this->assertStringContainsString('Name: John', $result);
        $this->assertStringContainsString('Middle:', $result); // Empty
        $this->assertStringContainsString('Last: Doe', $result);
    }

    #[Test]
    public function it_supports_blade_conditionals_with_both_shortcode_formats()
    {
        $template = <<<'BLADE'
@if($isPremium)
Premium User: {{ $user->first_name }}
Legacy: {{USER_EMAIL}}
@else
Regular User
@endif
BLADE;

        $resultPremium = $this->renderer->render($template, [
            'user' => ['first_name' => 'John', 'email' => 'john@example.com'],
            'isPremium' => true,
        ]);

        $resultRegular = $this->renderer->render($template, [
            'user' => ['first_name' => 'Jane', 'email' => 'jane@example.com'],
            'isPremium' => false,
        ]);

        $this->assertStringContainsString('Premium User: John', $resultPremium);
        $this->assertStringContainsString('john@example.com', $resultPremium);
        $this->assertStringContainsString('Regular User', $resultRegular);
        $this->assertStringNotContainsString('Premium User', $resultRegular);
    }

    #[Test]
    public function it_supports_blade_loops_with_shortcodes()
    {
        $template = <<<'BLADE'
Features:
@foreach($features as $feature)
- {{ $feature['name'] }} ({{ $feature['price'] }})
@endforeach
BLADE;

        $result = $this->renderer->render($template, [
            'features' => [
                ['name' => 'Feature A', 'price' => '$10'],
                ['name' => 'Feature B', 'price' => '$20'],
            ],
        ]);

        $this->assertStringContainsString('Feature A', $result);
        $this->assertStringContainsString('Feature B', $result);
        $this->assertStringContainsString('$10', $result);
        $this->assertStringContainsString('$20', $result);
    }

    #[Test]
    public function it_validates_template_syntax()
    {
        $validTemplate = 'Hello {{ $user->first_name }}';
        $result = $this->renderer->validate($validTemplate);

        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('error', $result);
    }

    #[Test]
    public function it_detects_invalid_template_syntax()
    {
        // Template with dangerous function
        $invalidTemplate = 'Hello {{ exec("ls") }}';
        $result = $this->renderer->validate($invalidTemplate);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function backward_compatibility_with_existing_notification_patterns()
    {
        // This mimics the pattern used in existing notifications
        $template = <<<'HTML'
<div>Dear <b>{{USER_FIRST_NAME}}</b>,</div>
<div><br></div>
<div>Thank you for choosing {{APP_NAME}}! Here are the details of your subscription plan:</div>
<div><br></div>
<div><strong>Plan</strong>: {{PLAN_LABEL}}<br></div>
<div><strong>Price</strong>: {{PLAN_PRICE}}<br></div>
<div><strong>Billing Cycle</strong>: {{PLAN_BILLING_CYCLE}}</div>
HTML;

        $result = $this->renderer->render($template, [
            'user' => ['first_name' => 'John', 'last_name' => 'Doe'],
            'app_name' => 'My App',
            'plan' => ['label' => 'Premium', 'price' => '$99', 'billing_cycle' => 'Monthly'],
        ]);

        $this->assertStringContainsString('<b>John</b>', $result);
        $this->assertStringContainsString('My App', $result);
        $this->assertStringContainsString('Premium', $result);
        $this->assertStringContainsString('$99', $result);
        $this->assertStringContainsString('Monthly', $result);
    }

    #[Test]
    public function blade_format_works_with_existing_notification_patterns()
    {
        // Modern Blade format
        $template = <<<'HTML'
<div>Dear <b>{{ $user->first_name }}</b>,</div>
<div><br></div>
<div>Thank you for choosing {{ $app->name }}! Here are the details of your subscription plan:</div>
<div><br></div>
<div><strong>Plan</strong>: {{ $plan->label }}<br></div>
<div><strong>Price</strong>: {{ $plan->price }}<br></div>
<div><strong>Billing Cycle</strong>: {{ $plan->billing_cycle }}</div>
HTML;

        $result = $this->renderer->render($template, [
            'user' => ['first_name' => 'John', 'last_name' => 'Doe'],
            'app' => ['name' => 'My App'],
            'plan' => ['label' => 'Premium', 'price' => '$99', 'billing_cycle' => 'Monthly'],
        ]);

        $this->assertStringContainsString('<b>John</b>', $result);
        $this->assertStringContainsString('My App', $result);
        $this->assertStringContainsString('$99', $result);
        $this->assertStringContainsString('Monthly', $result);
    }

    #[Test]
    public function it_renders_class_and_schedule_blade_template()
    {
        $template = <<<'HTML'
<div>Your class {{ $class->name }} starts at {{ $schedule->start_at }}. See you there!</div>
HTML;

        $class = new class
        {
            public $name = 'Math 101';
        };

        $schedule = new class
        {
            public $start_at = '2026-04-01 09:00:00';
        };

        $result = $this->renderer->render($template, [
            'class' => $class,
            'schedule' => $schedule,
        ]);

        $this->assertStringContainsString('Math 101', $result);
        $this->assertStringContainsString('2026-04-01 09:00:00', $result);
        $this->assertStringContainsString('See you there!', $result);
    }

    #[Test]
    public function it_handles_html_escaped_object_operator_in_templates()
    {
        // Template coming from a WYSIWYG editor that escaped '->' as '-&gt;'
        $template = <<<'HTML'
<div>Your class {{ $class-&gt;name }} starts at {{ $schedule-&gt;start_at }}. See you there!</div>
HTML;

        $class = new class
        {
            public $name = 'Physics 201';
        };

        $schedule = new class
        {
            public $start_at = '2026-05-10 14:30:00';
        };

        $result = $this->renderer->render($template, [
            'class' => $class,
            'schedule' => $schedule,
        ]);

        // Ensure escaped sequence is not present and values are rendered
        $this->assertStringNotContainsString('-&gt;', $result);
        $this->assertStringContainsString('Physics 201', $result);
        $this->assertStringContainsString('2026-05-10 14:30:00', $result);
        $this->assertStringContainsString('See you there!', $result);
    }
}
