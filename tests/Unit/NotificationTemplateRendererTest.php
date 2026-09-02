<?php

use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->renderer = app(NotificationTemplateRenderer::class);
});

it('renders legacy uppercase shortcodes for backward compatibility', function () {
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

    expect($result)->toContain('John');
    expect($result)->toContain('Premium Plan');
    expect($result)->toContain('$99/month');
    expect($result)->not->toContain('{{USER_FIRST_NAME}}');
    expect($result)->not->toContain('{{PLAN_LABEL}}');
});

it('renders blade variable syntax', function () {
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

    expect($result)->toContain('John');
    expect($result)->toContain('Premium Plan');
    expect($result)->toContain('$99/month');
});

it('supports mixing uppercase and blade formats', function () {
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

    expect($result)->toContain('Hello John Doe');
    expect($result)->toContain('john@example.com');
    expect($result)->toContain('Premium Plan');
    expect($result)->toContain('$99/month');
});

it('renders simple scalar values in uppercase format', function () {
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

    expect($result)->toContain('Custom Scalar: Test Value');
    expect($result)->toContain('Domain: example.com');
    expect($result)->toContain('Status: Active');
});

it('handles model objects with to array method', function () {
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

    expect($result)->toContain('Blade format: Jane jane@example.com');
    expect($result)->toContain('Legacy format: Smith');
});

it('handles nested array data', function () {
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

    expect($result)->toContain('User: Alice Johnson');
    expect($result)->toContain('Plan: Enterprise');
    expect($result)->toContain('Price: $199/month');
    expect($result)->toContain('Premium Member');
});

it('handles null values gracefully', function () {
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

    expect($result)->toContain('Name: John');
    expect($result)->toContain('Middle:');
    expect($result)->toContain('Last: Doe');
});

it('supports blade conditionals with both shortcode formats', function () {
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

    expect($resultPremium)->toContain('Premium User: John');
    expect($resultPremium)->toContain('john@example.com');
    expect($resultRegular)->toContain('Regular User');
    expect($resultRegular)->not->toContain('Premium User');
});

it('supports blade loops with shortcodes', function () {
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

    expect($result)->toContain('Feature A');
    expect($result)->toContain('Feature B');
    expect($result)->toContain('$10');
    expect($result)->toContain('$20');
});

it('validates template syntax', function () {
    $validTemplate = 'Hello {{ $user->first_name }}';
    $result = $this->renderer->validate($validTemplate);

    expect($result['valid'])->toBeTrue();
    expect($result)->not->toHaveKey('error');
});

it('detects invalid template syntax', function () {
    $invalidTemplate = 'Hello {{ exec("ls") }}';
    $result = $this->renderer->validate($invalidTemplate);

    expect($result['valid'])->toBeFalse();
    expect($result)->toHaveKey('error');
});

it('backward compatibility with existing notification patterns', function () {
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

    expect($result)->toContain('<b>John</b>');
    expect($result)->toContain('My App');
    expect($result)->toContain('Premium');
    expect($result)->toContain('$99');
    expect($result)->toContain('Monthly');
});

it('blade format works with existing notification patterns', function () {
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

    expect($result)->toContain('<b>John</b>');
    expect($result)->toContain('My App');
    expect($result)->toContain('$99');
    expect($result)->toContain('Monthly');
});

it('renders class and schedule blade template', function () {
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

    expect($result)->toContain('Math 101');
    expect($result)->toContain('2026-04-01 09:00:00');
    expect($result)->toContain('See you there!');
});

it('handles html escaped object operator in templates', function () {
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

    expect($result)->not->toContain('-&gt;');
    expect($result)->toContain('Physics 201');
    expect($result)->toContain('2026-05-10 14:30:00');
    expect($result)->toContain('See you there!');
});
