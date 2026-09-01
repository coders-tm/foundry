<?php

beforeEach(function () {
    $this->renderer = new \Foundry\Services\NotificationTemplateRenderer;
    $this->admin = \App\Models\Admin::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

uses(\Foundry\Tests\TestCase::class);

it('renders notification with blade directives', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Welcome {{ $userName }}',
        'content' => <<<'BLADE'
Hello {{ $userName }},

@if($hasPlan)
Your plan: {{ $planName }}
@endif

@foreach($features as $feature)
- {{ $feature }}
@endforeach

@php
    $greeting = "Thank you";
@endphp

{{ $greeting }} for joining us!
BLADE
    ]);

    $result = $notification->render([
        'userName' => 'John Doe',
        'hasPlan' => true,
        'planName' => 'Premium',
        'features' => ['Feature 1', 'Feature 2', 'Feature 3'],
    ]);

    $this->assertStringContainsString('John Doe', $result['subject']);
    $this->assertStringContainsString('John Doe', $result['content']);
    $this->assertStringContainsString('Premium', $result['content']);
    $this->assertStringContainsString('Feature 1', $result['content']);
    $this->assertStringContainsString('Thank you', $result['content']);
});

it('blocks dangerous functions in notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'Hello {{ exec("whoami") }}',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('not allowed for security reasons');

    $notification->render(['name' => 'Test']);
});

it('blocks dangerous functions inside php directive', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => '@php exec("whoami"); @endphp',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('not allowed for security reasons');

    $notification->render(['name' => 'Test']);
});

it('masks env calls in notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'Config: {{ env("APP_KEY") }}',
    ]);

    $result = $notification->render([]);

    $this->assertStringContainsString('****', $result['content']);
    $this->assertStringNotContainsString('APP_KEY', $result['content']);
});

it('masks settings calls in notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'Setting: {{ settings("database.password") }}',
    ]);

    $result = $notification->render([]);

    $this->assertStringContainsString('****', $result['content']);
});

it('masks sensitive config in notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'Secret: {{ config("app.key") }}',
    ]);

    $result = $notification->render([]);

    $this->assertStringContainsString('****', $result['content']);
    $this->assertStringNotContainsString('app.key', $result['content']);
});

it('allows safe config in notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'App: {{ config("app.name") }}',
    ]);

    $result = $notification->render([]);

    $this->assertStringContainsString(config('app.name'), $result['content']);
});

it('blocks mutation calls in notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => '@php config(["app.name" => "Hacked"]); @endphp',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Mutation calls');

    $notification->render([]);
});

it('validates safe notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Welcome',
        'content' => <<<'BLADE'
Hello {{ $name }},

@if($premium)
Thank you for being a premium member!
@endif

@php
    $message = "Enjoy your stay";
@endphp

{{ $message }}
BLADE
    ]);

    $result = $notification->validate();

    $this->assertTrue($result['subject']['valid']);
    $this->assertTrue($result['content']['valid']);
});

it('validates and rejects dangerous notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Welcome',
        'content' => 'Hello {{ exec("whoami") }}',
    ]);

    $result = $notification->validate();

    $this->assertTrue($result['subject']['valid']);
    $this->assertFalse($result['content']['valid']);
    $this->assertArrayHasKey('error', $result['content']);
});

it('supports shortcode replacement in notifications', function () {
    $template = <<<'BLADE'
Hello {{USER_FIRST_NAME}},

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
});

it('allows all safe blade directives in notifications', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test Subject',
        'content' => <<<'BLADE'
@section('greeting')
Hello {{ $name }}
@endsection

@yield('greeting')

@foreach($items as $item)
- {{ $item }}
@endforeach

@unless($premium)
Upgrade to premium!
@endunless

@isset($bonus)
Bonus: {{ $bonus }}
@endisset

@php
    $total = count($items);
@endphp

Total items: {{ $total }}

@once
This appears once
@endonce

@verbatim
{{ This is not parsed }}
@endverbatim
BLADE
    ]);

    $result = $notification->render([
        'name' => 'John',
        'items' => ['Item 1', 'Item 2'],
        'premium' => false,
        'bonus' => 'Free trial',
    ]);

    $this->assertStringContainsString('Test Subject', $result['subject']);
    $this->assertStringContainsString('John', $result['content']);
    $this->assertStringContainsString('Item 1', $result['content']);
    $this->assertStringContainsString('Upgrade to premium', $result['content']);
    $this->assertStringContainsString('Free trial', $result['content']);
});

it('blocks update function in notification template', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => '@php $user->update(["role" => "admin"]); @endphp',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('not allowed for security reasons');

    $notification->render(['user' => new \stdClass]);
});

it('masks env inside php blocks in notifications', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => <<<'BLADE'
@php
    $key = env("APP_KEY");
@endphp
Key: {{ $key }}
BLADE
    ]);

    $result = $notification->render([]);

    $this->assertStringContainsString('****', $result['content']);
});
