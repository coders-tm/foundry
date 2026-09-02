<?php

use Foundry\Models\Notification;
use Foundry\Services\MaskSensitiveConfig;
use Foundry\Services\NotificationTemplateRenderer;
use Foundry\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

uses(TestCase::class);

beforeEach(function () {
    $this->compiler = new MaskSensitiveConfig(
        app(Filesystem::class),
        storage_path('framework/views'),
    );
    $this->renderer = new NotificationTemplateRenderer;
});

it('allows php directive', function () {
    $template = "Hello @php echo 'ok'; @endphp";
    $compiled = $this->compiler->compileString($template);
    expect($compiled)->not->toBeEmpty();
    expect($compiled)->toContain("echo 'ok'");
});

it('allows include directive', function () {
    $template = "Hello @include('somefile')";
    $compiled = $this->compiler->compileString($template);
    expect($compiled)->not->toBeEmpty();
    expect($compiled)->toContain('$__env->make');
});

it('allows extends directive', function () {
    $template = "@extends('layout')";
    $compiled = $this->compiler->compileString($template);
    expect($compiled)->not->toBeEmpty();
});

it('allows section directive', function () {
    $template = "@section('content') test @endsection";
    $compiled = $this->compiler->compileString($template);
    expect($compiled)->not->toBeEmpty();
});

it('allows component directive', function () {
    $template = "@component('alert') test @endcomponent";
    $compiled = $this->compiler->compileString($template);
    expect($compiled)->not->toBeEmpty();
});

it('allows slot directive', function () {
    $template = "@slot('title') Test @endslot";
    $compiled = $this->compiler->compileString($template);
    expect($compiled)->not->toBeEmpty();
});

it('blocks exec function', function () {
    expect(fn () => $this->compiler->compileString("{{ exec('rm -rf /') }}"))->toThrow(InvalidArgumentException::class, "Function 'exec' is not allowed");
});

it('blocks shell exec function', function () {
    expect(fn () => $this->compiler->compileString("{{ shell_exec('whoami') }}"))->toThrow(InvalidArgumentException::class, "Function 'shell_exec' is not allowed");
});

it('blocks system function', function () {
    expect(fn () => $this->compiler->compileString("{{ system('ls') }}"))->toThrow(InvalidArgumentException::class, "Function 'system' is not allowed");
});

it('blocks eval function', function () {
    expect(fn () => $this->compiler->compileString("{{ eval('echo 1;') }}"))->toThrow(InvalidArgumentException::class, "Function 'eval' is not allowed");
});

it('blocks file get contents function', function () {
    expect(fn () => $this->compiler->compileString("{{ file_get_contents('/etc/passwd') }}"))->toThrow(InvalidArgumentException::class, "Function 'file_get_contents' is not allowed");
});

it('blocks file put contents function', function () {
    expect(fn () => $this->compiler->compileString("{{ file_put_contents('hack.php', '<?php') }}"))->toThrow(InvalidArgumentException::class, "Function 'file_put_contents' is not allowed");
});

it('blocks unlink function', function () {
    expect(fn () => $this->compiler->compileString("{{ unlink('important.txt') }}"))->toThrow(InvalidArgumentException::class, "Function 'unlink' is not allowed");
});

it('blocks db raw function', function () {
    expect(fn () => $this->compiler->compileString("{{ DB::raw('DROP TABLE users') }}"))->toThrow(InvalidArgumentException::class, "Function 'DB::raw' is not allowed");
});

it('blocks call user func', function () {
    expect(fn () => $this->compiler->compileString("{{ call_user_func('exec', 'whoami') }}"))->toThrow(InvalidArgumentException::class, "Function 'call_user_func' is not allowed");
});

it('strips php tags', function () {
    $template = "Hello <?php echo 'test'; ?> world";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->not->toContain('<?php');
    expect($compiled)->not->toContain('<?=');
});

it('masks env calls', function () {
    $template = "App key: {{ env('APP_KEY') }}";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->toContain("'****'");
});

it('masks env calls inside php', function () {
    $template = "@php echo env('APP_KEY'); @endphp";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->toContain("'****'");
});

it('masks sensitive config calls', function () {
    $template = "App key: {{ config('app.key') }}";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->toContain("'****'");
});

it('masks sensitive config calls inside php', function () {
    $template = "@php echo config('app.key'); @endphp";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->toContain("'****'");
});

it('masks database password config', function () {
    $template = "{{ config('database.connections.mysql.password') }}";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->toContain("'****'");
});

it('masks stripe secret config', function () {
    $template = "{{ config('services.stripe.secret') }}";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->toContain("'****'");
});

it('masks settings calls inside php', function () {
    $template = "@php echo settings('app.name'); @endphp";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->toContain("'****'");
});

it('blocks update calls inside php', function () {
    $template = '@php $user->update([\'name\' => \'x\']); @endphp';
    expect(fn () => $this->compiler->compileString($template))->toThrow(InvalidArgumentException::class);
});

it('blocks config set calls inside php', function () {
    $template = "@php Config::set('app.name', 'X'); @endphp";
    expect(fn () => $this->compiler->compileString($template))->toThrow(InvalidArgumentException::class);
});

it('blocks config array write calls inside php', function () {
    $template = "@php config(['app.name' => 'X']); @endphp";
    expect(fn () => $this->compiler->compileString($template))->toThrow(InvalidArgumentException::class);
});

it('allows safe if directive', function () {
    $template = '@if(true) Hello @endif';
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->not->toBeEmpty();
});

it('allows safe foreach directive', function () {
    $template = '@foreach($items as $item) {{ $item }} @endforeach';
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->not->toBeEmpty();
});

it('allows safe isset directive', function () {
    $template = '@isset($var) {{ $var }} @endisset';
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->not->toBeEmpty();
});

it('allows safe unless directive', function () {
    $template = '@unless($condition) text @endunless';
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->not->toBeEmpty();
});

it('allows safe empty directive', function () {
    $template = '@empty($items) No items @endempty';
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->not->toBeEmpty();
});

it('notification renderer blocks dangerous templates', function () {
    $template = "Hello @php exec('whoami'); @endphp";
    expect(fn () => $this->renderer->render($template, ['name' => 'User']))->toThrow(InvalidArgumentException::class);
});

it('notification renderer renders safe templates', function () {
    $template = 'Hello @if(true) {{ $name }} @endif';
    $result = $this->renderer->render($template, ['name' => 'John']);

    expect($result)->toContain('Hello');
    expect($result)->toContain('John');
});

it('notification renderer replaces shortcodes', function () {
    $template = 'Hello {{NAME}}, your order is {{ORDER_STATUS}}';

    $result = $this->renderer->render($template, [
        'name' => 'John',
        'order' => ['status' => 'completed', 'id' => 123],
    ]);

    expect($result)->toContain('John');
    expect($result)->toContain('completed');
});

it('notification renderer validates templates', function () {
    $safeTemplate = 'Hello @if(true) world @endif';
    $result = $this->renderer->validate($safeTemplate);

    expect($result['valid'])->toBeTrue();
});

it('notification renderer rejects invalid templates', function () {
    $dangerousTemplate = "Hello @php exec('hack'); @endphp";
    $result = $this->renderer->validate($dangerousTemplate);

    expect($result['valid'])->toBeFalse();
    expect($result)->toHaveKey('error');
});

it('notification model render uses secure renderer', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test Subject',
        'content' => 'Hello @if(true) {{ $name }} @endif',
    ]);

    $result = $notification->render(['name' => 'John']);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('subject');
    expect($result)->toHaveKey('content');
    expect($result['content'])->toContain('John');
});

it('notification model validate detects dangerous templates', function () {
    $notification = Notification::factory()->create([
        'type' => 'test',
        'subject' => 'Test',
        'content' => '@php exec("whoami"); @endphp',
    ]);

    $result = $notification->validate();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('content');
    expect($result['content']['valid'])->toBeFalse();
});

it('blocks multiple dangerous functions in one template', function () {
    $template = "{{ exec('ls') }} and {{ system('whoami') }}";
    expect(fn () => $this->compiler->compileString($template))->toThrow(InvalidArgumentException::class);
});

it('blocks case insensitive functions', function () {
    $template = "{{ EXEC('ls') }}";
    expect(fn () => $this->compiler->compileString($template))->toThrow(InvalidArgumentException::class);
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

    expect($compiled)->not->toBeEmpty();
});

it('blocks inline php short tags', function () {
    $template = "<?= 'test' ?>";
    $compiled = $this->compiler->compileString($template);

    expect($compiled)->not->toContain('<?=');
});
