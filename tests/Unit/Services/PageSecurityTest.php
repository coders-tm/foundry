<?php

namespace Foundry\Tests\Unit\Services;

use Coderstm\PageBuilder\Models\Page;
use Coderstm\PageBuilder\Services\PageStorage;
use Foundry\Services\MaskSensitiveConfig;
use Foundry\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for page content security via MaskSensitiveConfig.
 *
 * The new laravel-page-builder package stores pages as structured JSON
 * (sections/blocks) via PageStorage. Security is enforced at compile time
 * by MaskSensitiveConfig when template content is compiled through the
 * Blade compiler.
 */
class PageSecurityTest extends TestCase
{
    protected MaskSensitiveConfig $compiler;

    protected PageStorage $storage;

    protected array $savedSlugs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new MaskSensitiveConfig(
            app(Filesystem::class),
            storage_path('framework/views'),
        );
        $this->storage = app(PageStorage::class);
    }

    /**
     * Save page content as a simple HTML section via PageStorage.
     */
    private function savePage(string $slug, string $body, string $css = ''): void
    {
        $this->savedSlugs[] = $slug;

        $this->storage->save($slug, [
            'sections' => [
                'main' => [
                    'type' => 'html',
                    'settings' => ['content' => $body, 'css' => $css],
                ],
            ],
            'order' => ['main'],
        ]);
    }

    #[Test]
    public function it_allows_php_directive_in_pages()
    {
        $body = '@php $greeting = "Hello"; @endphp {{ $greeting }}';

        $compiled = $this->compiler->compileString($body);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_include_directive_in_pages()
    {
        $body = "Hello @include('somefile')";

        $compiled = $this->compiler->compileString($body);

        $this->assertNotEmpty($compiled);
        $this->assertStringContainsString('$__env->make', $compiled);
    }

    #[Test]
    public function it_allows_extends_directive_in_pages()
    {
        $body = "@extends('layout')";

        $compiled = $this->compiler->compileString($body);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_section_directive_in_pages()
    {
        $body = "@section('content') test @endsection";

        $compiled = $this->compiler->compileString($body);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_component_directive_in_pages()
    {
        $body = "@component('alert') test @endcomponent";

        $compiled = $this->compiler->compileString($body);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_slot_directive_in_pages()
    {
        $body = "@slot('title') Test @endslot";

        $compiled = $this->compiler->compileString($body);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_blocks_exec_function_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'exec' is not allowed");

        $this->compiler->compileString("{{ exec('rm -rf /') }}");
    }

    #[Test]
    public function it_blocks_shell_exec_function_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'shell_exec' is not allowed");

        $this->compiler->compileString("{{ shell_exec('whoami') }}");
    }

    #[Test]
    public function it_blocks_system_function_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'system' is not allowed");

        $this->compiler->compileString("{{ system('ls') }}");
    }

    #[Test]
    public function it_blocks_eval_function_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'eval' is not allowed");

        $this->compiler->compileString("{{ eval('echo 1;') }}");
    }

    #[Test]
    public function it_blocks_file_get_contents_function_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'file_get_contents' is not allowed");

        $this->compiler->compileString("{{ file_get_contents('/etc/passwd') }}");
    }

    #[Test]
    public function it_blocks_file_put_contents_function_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'file_put_contents' is not allowed");

        $this->compiler->compileString("{{ file_put_contents('hack.php', '<?php') }}");
    }

    #[Test]
    public function it_blocks_unlink_function_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'unlink' is not allowed");

        $this->compiler->compileString("{{ unlink('important.txt') }}");
    }

    #[Test]
    public function it_blocks_db_raw_function_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'DB::raw' is not allowed");

        $this->compiler->compileString("{{ DB::raw('DROP TABLE users') }}");
    }

    #[Test]
    public function it_blocks_call_user_func_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Function 'call_user_func' is not allowed");

        $this->compiler->compileString("{{ call_user_func('exec', 'whoami') }}");
    }

    #[Test]
    public function it_strips_php_tags_in_pages()
    {
        $body = "Hello <?php echo 'test'; ?> world";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringNotContainsString('<?php', $compiled);
        $this->assertStringNotContainsString('<?=', $compiled);
    }

    #[Test]
    public function it_blocks_inline_php_short_tags_in_pages()
    {
        $body = "<?= 'test' ?>";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringNotContainsString('<?=', $compiled);
    }

    #[Test]
    public function it_masks_env_calls_in_pages()
    {
        $body = "App key: {{ env('APP_KEY') }}";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_env_calls_inside_php_in_pages()
    {
        $body = "@php echo env('APP_KEY'); @endphp";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_sensitive_config_calls_in_pages()
    {
        $body = "App key: {{ config('app.key') }}";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_sensitive_config_calls_inside_php_in_pages()
    {
        $body = "@php echo config('app.key'); @endphp";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_database_password_config_in_pages()
    {
        $body = "{{ config('database.connections.mysql.password') }}";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_stripe_secret_config_in_pages()
    {
        $body = "{{ config('services.stripe.secret') }}";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_masks_settings_calls_inside_php_in_pages()
    {
        $body = "@php echo settings('app.name'); @endphp";

        $compiled = $this->compiler->compileString($body);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function it_blocks_update_calls_inside_php_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->compiler->compileString('@php $user->update([\'name\' => \'x\']); @endphp');
    }

    #[Test]
    public function it_blocks_config_set_calls_inside_php_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->compiler->compileString("@php Config::set('app.name', 'X'); @endphp");
    }

    #[Test]
    public function it_blocks_config_array_write_calls_inside_php_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->compiler->compileString("@php config(['app.name' => 'X']); @endphp");
    }

    #[Test]
    public function it_allows_safe_if_directive_in_pages()
    {
        $compiled = $this->compiler->compileString('@if(true) Hello @endif');

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_foreach_directive_in_pages()
    {
        $compiled = $this->compiler->compileString('@foreach($items as $item) {{ $item }} @endforeach');

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_isset_directive_in_pages()
    {
        $compiled = $this->compiler->compileString('@isset($var) {{ $var }} @endisset');

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_unless_directive_in_pages()
    {
        $compiled = $this->compiler->compileString('@unless($condition) text @endunless');

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_empty_directive_in_pages()
    {
        $compiled = $this->compiler->compileString('@empty($items) No items @endempty');

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_blocks_multiple_dangerous_functions_in_one_page()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->compiler->compileString("{{ exec('ls') }} and {{ system('whoami') }}");
    }

    #[Test]
    public function it_blocks_case_insensitive_functions_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->compiler->compileString("{{ EXEC('ls') }}");
    }

    #[Test]
    public function complex_page_with_allowed_directives_works()
    {
        $body = <<<'BLADE'
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

        $compiled = $this->compiler->compileString($body);

        $this->assertNotEmpty($compiled);
    }

    #[Test]
    public function it_allows_safe_css_in_pages()
    {
        $slug = 'safe-css-test';
        $body = '<div class="box">Content</div>';
        $css = '.box { color: blue; background: #fff; }';

        $this->savePage($slug, $body, $css);

        $pageData = $this->storage->load($slug);

        $this->assertNotNull($pageData);
        $this->assertNotEmpty($pageData->sections());
    }

    #[Test]
    public function it_blocks_dangerous_php_in_css_in_pages()
    {
        $this->expectException(\InvalidArgumentException::class);

        $css = '.x { content: "{{ exec(\'id\') }}"; }';
        $this->compiler->compileString($css);
    }

    #[Test]
    public function it_masks_env_and_config_calls_in_css_in_pages()
    {
        $css = '.x { --secret: "{{ env(\'APP_KEY\') }} {{ config(\'app.key\') }}"; }';

        $compiled = $this->compiler->compileString($css);

        $this->assertStringContainsString("'****'", $compiled);
    }

    #[Test]
    public function page_can_be_created_and_content_saved_via_storage()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'storage-test',
            'is_active' => true,
        ]);

        $slug = $page->slug;
        $this->savedSlugs[] = $slug;

        $this->storage->save($slug, [
            'sections' => [
                'hero' => [
                    'type' => 'hero',
                    'settings' => ['heading' => 'Welcome', 'subheading' => 'Hello world'],
                ],
            ],
            'order' => ['hero'],
        ]);

        $loaded = $this->storage->load($slug);

        $this->assertNotNull($loaded);
        $this->assertArrayHasKey('hero', $loaded->sections());
        $this->assertEquals(['hero'], $loaded->order());
    }

    #[Test]
    public function page_storage_strips_title_and_meta_from_json()
    {
        $slug = 'meta-strip-test';
        $this->savedSlugs[] = $slug;

        $this->storage->save($slug, [
            'title' => 'Should be stripped',
            'meta' => ['meta_title' => 'Also stripped'],
            'sections' => ['main' => ['type' => 'html', 'settings' => []]],
            'order' => ['main'],
        ]);

        $loaded = $this->storage->load($slug);

        $this->assertNotNull($loaded);
        $this->assertEmpty($loaded->title()); // title is stripped from JSON
    }

    protected function tearDown(): void
    {
        $pagesPath = config('pagebuilder.pages');

        foreach ($this->savedSlugs as $slug) {
            $file = $pagesPath . '/' . $slug . '.json';
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        parent::tearDown();
    }
}
