<?php

use App\Models\Admin;
use Foundry\Models\Notification;
use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Tests\TestCase;

beforeEach(function () {
    $this->renderer = new NotificationTemplateRenderer;
    $this->admin = Admin::factory()->create(['is_super_admin' => true]);
    $this->actingAs($this->admin);
});

uses(TestCase::class);

it('renders notification with blade directives', function () {
    $notification = Notification::factory()->create([
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

    expect($result['subject'])->toContain('John Doe');
    expect($result['content'])->toContain('John Doe');
    expect($result['content'])->toContain('Premium');
    expect($result['content'])->toContain('Feature 1');
    expect($result['content'])->toContain('Thank you');
});

it('blocks dangerous functions in notification template', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'Hello {{ exec("whoami") }}',
    ]);

    expect(fn () => $notification->render(['name' => 'Test']))->toThrow(InvalidArgumentException::class, 'not allowed for security reasons');
});

it('blocks dangerous functions inside php directive', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => '@php exec("whoami"); @endphp',
    ]);

    expect(fn () => $notification->render(['name' => 'Test']))->toThrow(InvalidArgumentException::class, 'not allowed for security reasons');
});

it('masks env calls in notification template', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'Config: {{ env("APP_KEY") }}',
    ]);

    $result = $notification->render([]);

    expect($result['content'])->toContain('****');
    expect($result['content'])->not->toContain('APP_KEY');
});

it('masks settings calls in notification template', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'Setting: {{ settings("database.password") }}',
    ]);

    $result = $notification->render([]);

    expect($result['content'])->toContain('****');
});

it('masks sensitive config in notification template', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'Secret: {{ config("app.key") }}',
    ]);

    $result = $notification->render([]);

    expect($result['content'])->toContain('****');
    expect($result['content'])->not->toContain('app.key');
});

it('allows safe config in notification template', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => 'App: {{ config("app.name") }}',
    ]);

    $result = $notification->render([]);

    expect($result['content'])->toContain(config('app.name'));
});

it('blocks mutation calls in notification template', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => '@php config(["app.name" => "Hacked"]); @endphp',
    ]);

    expect(fn () => $notification->render([]))->toThrow(InvalidArgumentException::class, 'Mutation calls');
});

it('validates safe notification template', function () {
    $notification = Notification::factory()->create([
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

    expect($result['subject']['valid'])->toBeTrue();
    expect($result['content']['valid'])->toBeTrue();
});

it('validates and rejects dangerous notification template', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Welcome',
        'content' => 'Hello {{ exec("whoami") }}',
    ]);

    $result = $notification->validate();

    expect($result['subject']['valid'])->toBeTrue();
    expect($result['content']['valid'])->toBeFalse();
    expect($result['content'])->toHaveKey('error');
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

    expect($result)->toContain('John');
    expect($result)->toContain('Premium Plan');
    expect($result)->toContain('$99/month');
});

it('allows all safe blade directives in notifications', function () {
    $notification = Notification::factory()->create([
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

    expect($result['subject'])->toContain('Test Subject');
    expect($result['content'])->toContain('John');
    expect($result['content'])->toContain('Item 1');
    expect($result['content'])->toContain('Upgrade to premium');
    expect($result['content'])->toContain('Free trial');
});

it('blocks update function in notification template', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => '@php $user->update(["role" => "admin"]); @endphp',
    ]);

    expect(fn () => $notification->render(['user' => new stdClass]))->toThrow(InvalidArgumentException::class, 'not allowed for security reasons');
});

it('masks env inside php blocks in notifications', function () {
    $notification = Notification::factory()->create([
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

    expect($result['content'])->toContain('****');
});
