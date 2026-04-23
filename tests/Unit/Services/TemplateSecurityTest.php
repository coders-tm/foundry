<?php

namespace Foundry\Tests\Unit\Services;

use Foundry\Models\Notification;
use Foundry\Services\MaskSensitiveConfig;
use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;

class TemplateSecurityTest extends TestCase
{
    protected MaskSensitiveConfig $compiler;

    protected NotificationTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        // Default compiler (now MaskSensitiveConfig with unified security)
        $this->compiler = new MaskSensitiveConfig(
            app(Filesystem::class),
            storage_path('framework/views'),
        );

        $this->renderer = new NotificationTemplateRenderer;
    }

    #[Test]
    public function it_allows_php_directive()
    {
        $template = "Hello @php echo 'ok'; @endphp";
        $compiled = $this->compiler->compileString($template);
        $this->assertNotEmpty($compiled);
        $this->assertStringContainsString("echo 'ok'", $compiled);
    }

    #[Test]
    public function it_allows_include_directive()
    {
        $template = "Hello @include('somefile')";

        // Should compile without error (allows @include) - compiles to $__env->make()
        $compiled = $this->compiler->compileString($template);
        $this->assertNotEmpty($compiled);
        $this->assertStringContainsString('$__env->make', $compiled);
    }

    #[Test]
    public function it_allows_extends_directive()
    {
        $template = "@extends('layout')";

        // Should compile without error (allows @extends)
        $compiled = $this->compiler->compileString($template);
        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_section_directive()
    {
        $template = "@section('content') test @endsection";

        // Should compile without error (allows @section)
        $compiled = $this->compiler->compileString($template);
        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_component_directive()
    {
        $template = "@component('alert') test @endcomponent";

        // Should compile without error (allows @component)
        $compiled = $this->compiler->compileString($template);
        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_slot_directive()
    {
        $template = "@slot('title') Test @endslot";

        // Should compile without error (allows @slot)
        $compiled = $this->compiler->compileString($template);
        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_blocks_exec_function()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'exec' is not allowed");

        $template = "{{ exec('rm -rf /') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_shell_exec_function()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'shell_exec' is not allowed");

        $template = "{{ shell_exec('whoami') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_system_function()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'system' is not allowed");

        $template = "{{ system('ls') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_eval_function()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'eval' is not allowed");

        $template = "{{ eval('echo 1;') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_file_get_contents_function()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'file_get_contents' is not allowed");

        $template = "{{ file_get_contents('/etc/passwd') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_file_put_contents_function()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'file_put_contents' is not allowed");

        $template = "{{ file_put_contents('hack.php', '<?php') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_unlink_function()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'unlink' is not allowed");

        $template = "{{ unlink('important.txt') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_db_raw_function()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'DB::raw' is not allowed");

        $template = "{{ DB::raw('DROP TABLE users') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_call_user_func()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'call_user_func' is not allowed");

        $template = "{{ call_user_func('exec', 'whoami') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_strips_php_tags()
    {
        $template = "Hello <?php echo 'test'; ?> world";
        $compiled = $this->compiler->compileString($template);

        $this->assertStringNotContainsString('<?php', $compiled);
        $this->assertStringNotContainsString('<?=', $compiled);
    }

    #[Test]
    public function it_masks_env_calls()
    {
        $template = "App key: {{ env('APP_KEY') }}";
        $compiled = $this->compiler->compileString($template);

        // env() calls should be masked
        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_env_calls_inside_php()
    {
        $template = "@php echo env('APP_KEY'); @endphp";
        $compiled = $this->compiler->compileString($template);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_sensitive_config_calls()
    {
        $template = "App key: {{ config('app.key') }}";
        $compiled = $this->compiler->compileString($template);

        // Sensitive config should be masked
        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_sensitive_config_calls_inside_php()
    {
        $template = "@php echo config('app.key'); @endphp";
        $compiled = $this->compiler->compileString($template);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_database_password_config()
    {
        $template = "{{ config('database.connections.mysql.password') }}";
        $compiled = $this->compiler->compileString($template);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_stripe_secret_config()
    {
        $template = "{{ config('services.stripe.secret') }}";
        $compiled = $this->compiler->compileString($template);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_settings_calls_inside_php()
    {
        $template = "@php echo settings('app.name'); @endphp";
        $compiled = $this->compiler->compileString($template);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_blocks_update_calls_inside_php()
    {
        $this->expectException(\InvalidArgumentException::class);
        $template = '@php $user->update([\'name\' => \'x\']); @endphp';
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_config_set_calls_inside_php()
    {
        $this->expectException(\InvalidArgumentException::class);
        $template = "@php Config::set('app.name', 'X'); @endphp";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_config_array_write_calls_inside_php()
    {
        $this->expectException(\InvalidArgumentException::class);
        $template = "@php config(['app.name' => 'X']); @endphp";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_allows_safe_if_directive()
    {
        $template = '@if(true) Hello @endif';
        $compiled = $this->compiler->compileString($template);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_foreach_directive()
    {
        $template = '@foreach($items as $item) {{ $item }} @endforeach';
        $compiled = $this->compiler->compileString($template);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_isset_directive()
    {
        $template = '@isset($var) {{ $var }} @endisset';
        $compiled = $this->compiler->compileString($template);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_unless_directive()
    {
        $template = '@unless($condition) text @endunless';
        $compiled = $this->compiler->compileString($template);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_empty_directive()
    {
        $template = '@empty($items) No items @endempty';
        $compiled = $this->compiler->compileString($template);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function notification_renderer_blocks_dangerous_templates()
    {
        $this->expectException(\InvalidArgumentException::class);

        // Still blocks dangerous functions inside @php
        $template = "Hello @php exec('whoami'); @endphp";
        $this->renderer->render($template, ['name' => 'User']);
    }

    #[Test]
    public function notification_renderer_renders_safe_templates()
    {
        $template = 'Hello @if(true) {{ $name }} @endif';
        $result = $this->renderer->render($template, ['name' => 'John']);

        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('John', $result);
    }

    #[Test]
    public function notification_renderer_replaces_shortcodes()
    {
        $template = 'Hello {{NAME}}, your order is {{ORDER_STATUS}}';

        // Use array for shortcode replacement (UPPERCASE format)
        $result = $this->renderer->render($template, [
            'name' => 'John',
            'order' => ['status' => 'completed', 'id' => 123],
        ]);

        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('completed', $result);
    }

    #[Test]
    public function notification_renderer_validates_templates()
    {
        $safeTemplate = 'Hello @if(true) world @endif';
        $result = $this->renderer->validate($safeTemplate);

        $this->assertTrue($result['valid']);
    }

    #[Test]
    public function notification_renderer_rejects_invalid_templates()
    {
        // Dangerous due to exec()
        $dangerousTemplate = "Hello @php exec('hack'); @endphp";
        $result = $this->renderer->validate($dangerousTemplate);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function notification_model_render_uses_secure_renderer()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test Subject',
            'content' => 'Hello @if(true) {{ $name }} @endif',
        ]);

        $result = $notification->render(['name' => 'John']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertStringContainsString('John', $result['content']);
    }

    #[Test]
    public function notification_model_validate_detects_dangerous_templates()
    {
        $notification = Notification::factory()->create([
            'type' => 'test',
            'subject' => 'Test',
            // Dangerous due to exec()
            'content' => '@php exec("whoami"); @endphp',
        ]);

        $result = $notification->validate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertFalse($result['content']['valid']);
    }

    #[Test]
    public function it_blocks_multiple_dangerous_functions_in_one_template()
    {
        $this->expectException(\InvalidArgumentException::class);

        $template = "{{ exec('ls') }} and {{ system('whoami') }}";
        $this->compiler->compileString($template);
    }

    #[Test]
    public function it_blocks_case_insensitive_functions()
    {
        $this->expectException(\InvalidArgumentException::class);

        $template = "{{ EXEC('ls') }}"; // Uppercase
        $this->compiler->compileString($template);
    }

    #[Test]
    public function complex_template_with_allowed_directives_works()
    {
        $template = <<<'BLADE'
@if($user)
    Hello {{ $user->name }}

    @foreach($orders as $order)
        Order #{{ $order->id }}: {{ $order->status }}

        @isset($order->notes)
            Notes: {{ $order->notes }}
        @endisset
    @endforeach

    @unless($user->subscribed)
        Please subscribe!
    @endunless
@endif
BLADE;

        $compiled = $this->compiler->compileString($template);

        // Should compile successfully without throwing exceptions
        $this->assertNotEmpty($compiled);

        // We allow @php directives; this template doesn't use them.
    }

    #[Test]
    public function it_blocks_inline_php_short_tags()
    {
        $template = "<?= 'test' ?>";
        $compiled = $this->compiler->compileString($template);

        $this->assertStringNotContainsString('<?=', $compiled);
    }
}
