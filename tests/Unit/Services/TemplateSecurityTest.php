<?php

uses(Foundry\Tests\TestCase::class);

beforeEach(function () {
    $this->compiler = new \Foundry\Services\MaskSensitiveConfig(
        app(\Illuminate\Filesystem\Filesystem::class),
        storage_path('framework/views'),
    );
    $this->renderer = new \Foundry\Services\NotificationTemplateRenderer;
});

it('allows php directive', function () {
    $template = "Hello @php echo 'ok'; @endphp";
    $compiled = $this->compiler->compileString($template);
    $this->assertNotEmpty($compiled);
    $this->assertStringContainsString("echo 'ok'", $compiled);
});

it('allows include directive', function () {
    $template = "Hello @include('somefile')";
    $compiled = $this->compiler->compileString($template);
    $this->assertNotEmpty($compiled);
    $this->assertStringContainsString('$__env->make', $compiled);
});

it('allows extends directive', function () {
    $template = "@extends('layout')";
    $compiled = $this->compiler->compileString($template);
    $this->assertNotEmpty($compiled);
});

it('allows section directive', function () {
    $template = "@section('content') test @endsection";
    $compiled = $this->compiler->compileString($template);
    $this->assertNotEmpty($compiled);
});

it('allows component directive', function () {
    $template = "@component('alert') test @endcomponent";
    $compiled = $this->compiler->compileString($template);
    $this->assertNotEmpty($compiled);
});

it('allows slot directive', function () {
    $template = "@slot('title') Test @endslot";
    $compiled = $this->compiler->compileString($template);
    $this->assertNotEmpty($compiled);
});

it('blocks exec function', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'exec' is not allowed");

    $this->compiler->compileString("{{ exec('rm -rf /') }}");
});

it('blocks shell exec function', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'shell_exec' is not allowed");

    $this->compiler->compileString("{{ shell_exec('whoami') }}");
});

it('blocks system function', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'system' is not allowed");

    $this->compiler->compileString("{{ system('ls') }}");
});

it('blocks eval function', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'eval' is not allowed");

    $this->compiler->compileString("{{ eval('echo 1;') }}");
});

it('blocks file get contents function', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'file_get_contents' is not allowed");

    $this->compiler->compileString("{{ file_get_contents('/etc/passwd') }}");
});

it('blocks file put contents function', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'file_put_contents' is not allowed");

    $this->compiler->compileString("{{ file_put_contents('hack.php', '<?php') }}");
});

it('blocks unlink function', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'unlink' is not allowed");

    $this->compiler->compileString("{{ unlink('important.txt') }}");
});

it('blocks db raw function', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'DB::raw' is not allowed");

    $this->compiler->compileString("{{ DB::raw('DROP TABLE users') }}");
});

it('blocks call user func', function () {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Function 'call_user_func' is not allowed");

    $this->compiler->compileString("{{ call_user_func('exec', 'whoami') }}");
});

it('strips php tags', function () {
    $template = "Hello <?php echo 'test'; ?> world";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringNotContainsString('<?php', $compiled);
    $this->assertStringNotContainsString('<?=', $compiled);
});

it('masks env calls', function () {
    $template = "App key: {{ env('APP_KEY') }}";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringContainsString("'****'", $compiled);
});

it('masks env calls inside php', function () {
    $template = "@php echo env('APP_KEY'); @endphp";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringContainsString("'****'", $compiled);
});

it('masks sensitive config calls', function () {
    $template = "App key: {{ config('app.key') }}";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringContainsString("'****'", $compiled);
});

it('masks sensitive config calls inside php', function () {
    $template = "@php echo config('app.key'); @endphp";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringContainsString("'****'", $compiled);
});

it('masks database password config', function () {
    $template = "{{ config('database.connections.mysql.password') }}";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringContainsString("'****'", $compiled);
});

it('masks stripe secret config', function () {
    $template = "{{ config('services.stripe.secret') }}";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringContainsString("'****'", $compiled);
});

it('masks settings calls inside php', function () {
    $template = "@php echo settings('app.name'); @endphp";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringContainsString("'****'", $compiled);
});

it('blocks update calls inside php', function () {
    $this->expectException(\InvalidArgumentException::class);
    $template = '@php $user->update([\'name\' => \'x\']); @endphp';
    $this->compiler->compileString($template);
});

it('blocks config set calls inside php', function () {
    $this->expectException(\InvalidArgumentException::class);
    $template = "@php Config::set('app.name', 'X'); @endphp";
    $this->compiler->compileString($template);
});

it('blocks config array write calls inside php', function () {
    $this->expectException(\InvalidArgumentException::class);
    $template = "@php config(['app.name' => 'X']); @endphp";
    $this->compiler->compileString($template);
});

it('allows safe if directive', function () {
    $template = '@if(true) Hello @endif';
    $compiled = $this->compiler->compileString($template);

    $this->assertNotEmpty($compiled);
});

it('allows safe foreach directive', function () {
    $template = '@foreach($items as $item) {{ $item }} @endforeach';
    $compiled = $this->compiler->compileString($template);

    $this->assertNotEmpty($compiled);
});

it('allows safe isset directive', function () {
    $template = '@isset($var) {{ $var }} @endisset';
    $compiled = $this->compiler->compileString($template);

    $this->assertNotEmpty($compiled);
});

it('allows safe unless directive', function () {
    $template = '@unless($condition) text @endunless';
    $compiled = $this->compiler->compileString($template);

    $this->assertNotEmpty($compiled);
});

it('allows safe empty directive', function () {
    $template = '@empty($items) No items @endempty';
    $compiled = $this->compiler->compileString($template);

    $this->assertNotEmpty($compiled);
});

it('notification renderer blocks dangerous templates', function () {
    $this->expectException(\InvalidArgumentException::class);

    $template = "Hello @php exec('whoami'); @endphp";
    $this->renderer->render($template, ['name' => 'User']);
});

it('notification renderer renders safe templates', function () {
    $template = 'Hello @if(true) {{ $name }} @endif';
    $result = $this->renderer->render($template, ['name' => 'John']);

    $this->assertStringContainsString('Hello', $result);
    $this->assertStringContainsString('John', $result);
});

it('notification renderer replaces shortcodes', function () {
    $template = 'Hello {{NAME}}, your order is {{ORDER_STATUS}}';

    $result = $this->renderer->render($template, [
        'name' => 'John',
        'order' => ['status' => 'completed', 'id' => 123],
    ]);

    $this->assertStringContainsString('John', $result);
    $this->assertStringContainsString('completed', $result);
});

it('notification renderer validates templates', function () {
    $safeTemplate = 'Hello @if(true) world @endif';
    $result = $this->renderer->validate($safeTemplate);

    $this->assertTrue($result['valid']);
});

it('notification renderer rejects invalid templates', function () {
    $dangerousTemplate = "Hello @php exec('hack'); @endphp";
    $result = $this->renderer->validate($dangerousTemplate);

    $this->assertFalse($result['valid']);
    $this->assertArrayHasKey('error', $result);
});

it('notification model render uses secure renderer', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test Subject',
        'content' => 'Hello @if(true) {{ $name }} @endif',
    ]);

    $result = $notification->render(['name' => 'John']);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('subject', $result);
    $this->assertArrayHasKey('content', $result);
    $this->assertStringContainsString('John', $result['content']);
});

it('notification model validate detects dangerous templates', function () {
    $notification = \Foundry\Models\Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => '@php exec("whoami"); @endphp',
    ]);

    $result = $notification->validate();

    $this->assertIsArray($result);
    $this->assertArrayHasKey('content', $result);
    $this->assertFalse($result['content']['valid']);
});

it('blocks multiple dangerous functions in one template', function () {
    $this->expectException(\InvalidArgumentException::class);

    $template = "{{ exec('ls') }} and {{ system('whoami') }}";
    $this->compiler->compileString($template);
});

it('blocks case insensitive functions', function () {
    $this->expectException(\InvalidArgumentException::class);

    $template = "{{ EXEC('ls') }}";
    $this->compiler->compileString($template);
});

it('complex template with allowed directives works', function () {
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

    $this->assertNotEmpty($compiled);
});

it('blocks inline php short tags', function () {
    $template = "<?= 'test' ?>";
    $compiled = $this->compiler->compileString($template);

    $this->assertStringNotContainsString('<?=', $compiled);
});
