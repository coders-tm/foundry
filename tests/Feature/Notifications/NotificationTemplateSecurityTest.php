<?php

namespace Foundry\Tests\Feature\Notifications;

use App\Models\Admin;
use Foundry\Models\Notification;
use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NotificationTemplateSecurityTest extends TestCase
{
    protected NotificationTemplateRenderer $renderer;

    protected \Foundry\Models\Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new NotificationTemplateRenderer;
        $this->admin = Admin::factory()->create(['is_super_admin' => true]);
        $this->actingAs($this->admin);
    }

    #[Test]
    public function it_renders_notification_with_blade_directives()
    {
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

        $this->assertStringContainsString('John Doe', $result['subject']);
        $this->assertStringContainsString('John Doe', $result['content']);
        $this->assertStringContainsString('Premium', $result['content']);
        $this->assertStringContainsString('Feature 1', $result['content']);
        $this->assertStringContainsString('Thank you', $result['content']);
    }

    #[Test]
    public function it_blocks_dangerous_functions_in_notification_template()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            'content' => 'Hello {{ exec("whoami") }}',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed for security reasons');

        $notification->render(['name' => 'Test']);
    }

    #[Test]
    public function it_blocks_dangerous_functions_inside_php_directive()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            'content' => '@php exec("whoami"); @endphp',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed for security reasons');

        $notification->render(['name' => 'Test']);
    }

    #[Test]
    public function it_masks_env_calls_in_notification_template()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            'content' => 'Config: {{ env("APP_KEY") }}',
        ]);

        $result = $notification->render([]);

        // env() should be masked
        $this->assertStringContainsString('****', $result['content']);
        $this->assertStringNotContainsString('APP_KEY', $result['content']);
    }

    #[Test]
    public function it_masks_settings_calls_in_notification_template()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            'content' => 'Setting: {{ settings("database.password") }}',
        ]);

        $result = $notification->render([]);

        // settings() should be masked
        $this->assertStringContainsString('****', $result['content']);
    }

    #[Test]
    public function it_masks_sensitive_config_in_notification_template()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            'content' => 'Secret: {{ config("app.key") }}',
        ]);

        $result = $notification->render([]);

        // Sensitive config should be masked
        $this->assertStringContainsString('****', $result['content']);
        $this->assertStringNotContainsString('app.key', $result['content']);
    }

    #[Test]
    public function it_allows_safe_config_in_notification_template()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            'content' => 'App: {{ config("app.name") }}',
        ]);

        $result = $notification->render([]);

        // Non-sensitive config should work
        $this->assertStringContainsString(config('app.name'), $result['content']);
    }

    #[Test]
    public function it_blocks_mutation_calls_in_notification_template()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            'content' => '@php config(["app.name" => "Hacked"]); @endphp',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Mutation calls');

        $notification->render([]);
    }

    #[Test]
    public function it_validates_safe_notification_template()
    {
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

        $this->assertTrue($result['subject']['valid']);
        $this->assertTrue($result['content']['valid']);
    }

    #[Test]
    public function it_validates_and_rejects_dangerous_notification_template()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Welcome',
            'content' => 'Hello {{ exec("whoami") }}',
        ]);

        $result = $notification->validate();

        $this->assertTrue($result['subject']['valid']);
        $this->assertFalse($result['content']['valid']);
        $this->assertArrayHasKey('error', $result['content']);
    }

    #[Test]
    public function it_supports_shortcode_replacement_in_notifications()
    {
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
    }

    #[Test]
    public function it_allows_all_safe_blade_directives_in_notifications()
    {
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

        $this->assertStringContainsString('Test Subject', $result['subject']);
        $this->assertStringContainsString('John', $result['content']);
        $this->assertStringContainsString('Item 1', $result['content']);
        $this->assertStringContainsString('Upgrade to premium', $result['content']);
        $this->assertStringContainsString('Free trial', $result['content']);
    }

    #[Test]
    public function it_blocks_update_function_in_notification_template()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            'content' => '@php $user->update(["role" => "admin"]); @endphp',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed for security reasons');

        $notification->render(['user' => new \stdClass]);
    }

    #[Test]
    public function it_masks_env_inside_php_blocks_in_notifications()
    {
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

        // env() should be masked even inside @php
        $this->assertStringContainsString('****', $result['content']);
    }
}
