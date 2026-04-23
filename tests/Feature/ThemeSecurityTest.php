<?php

namespace Foundry\Tests\Feature;

use Foundry\Models\Admin;
use Foundry\PageBuilder\Facades\Theme;
use Foundry\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

class ThemeSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $testTheme = 'test-security-theme';

    protected function setUp(): void
    {
        parent::setUp();
        /** @var Admin $this->admin */
        $this->admin = Admin::factory()->create(['is_super_admin' => true]);
        $this->actingAs($this->admin, 'admin');
        $this->createTestTheme();
    }

    protected function tearDown(): void
    {
        $themePath = Theme::basePath('', $this->testTheme);
        if (File::exists($themePath)) {
            File::deleteDirectory($themePath);
        }
        parent::tearDown();
    }

    protected function createTestTheme()
    {
        $themePath = Theme::basePath('', $this->testTheme);
        File::makeDirectory($themePath, 0755, true, true);
        File::makeDirectory($themePath.'/views', 0755, true, true);

        $config = ['name' => 'Test Theme', 'version' => '1.0.0'];
        File::put($themePath.'/config.json', json_encode($config));
        File::put($themePath.'/views/test.blade.php', '<h1>Test</h1>');
    }

    #[Test]
    public function it_blocks_exec_function_in_theme_file()
    {
        $response = $this->postJson("/admin/themes/{$this->testTheme}/files", [
            'key' => 'views/test.blade.php',
            'content' => '@php exec("rm -rf /"); @endphp',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Security or syntax error', $response->json('message'));
    }

    #[Test]
    public function it_blocks_shell_exec_in_theme_file()
    {
        $response = $this->postJson("/admin/themes/{$this->testTheme}/files", [
            'key' => 'views/test.blade.php',
            'content' => '@php shell_exec("cat /etc/passwd"); @endphp',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Security or syntax error', $response->json('message'));
    }

    #[Test]
    public function it_blocks_system_in_theme_file()
    {
        $response = $this->postJson("/admin/themes/{$this->testTheme}/files", [
            'key' => 'views/test.blade.php',
            'content' => '@php system("whoami"); @endphp',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Security or syntax error', $response->json('message'));
    }

    #[Test]
    public function it_blocks_eval_in_theme_file()
    {
        $response = $this->postJson("/admin/themes/{$this->testTheme}/files", [
            'key' => 'views/test.blade.php',
            'content' => '@php eval($_GET["cmd"]); @endphp',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Security or syntax error', $response->json('message'));
    }

    #[Test]
    public function it_blocks_file_get_contents_in_theme_file()
    {
        $response = $this->postJson("/admin/themes/{$this->testTheme}/files", [
            'key' => 'views/test.blade.php',
            'content' => '@php $data = file_get_contents("/etc/passwd"); @endphp',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Security or syntax error', $response->json('message'));
    }

    #[Test]
    public function it_blocks_db_raw_in_theme_file()
    {
        $response = $this->postJson("/admin/themes/{$this->testTheme}/files", [
            'key' => 'views/test.blade.php',
            'content' => '@php DB::raw("DROP TABLE users"); @endphp',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('Security or syntax error', $response->json('message'));
    }

    #[Test]
    public function it_allows_safe_blade_directives()
    {
        $response = $this->postJson("/admin/themes/{$this->testTheme}/files", [
            'key' => 'views/test.blade.php',
            'content' => '<h1>{{ $title }}</h1>@if($show)<p>Safe</p>@endif',
        ]);

        $response->assertStatus(200)->assertJson(['message' => 'File saved successfully']);
    }
}
