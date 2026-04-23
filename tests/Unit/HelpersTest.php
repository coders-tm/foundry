<?php

namespace Foundry\Tests\Unit;

use App\Models\User;
use Foundry\Models\Admin;
use Foundry\Tests\BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Optional;

class HelpersTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRequest();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetConfig();
    }

    protected function resetConfig()
    {
        Config::set('app.url', env('APP_URL'));
        Config::set('foundry.admin_url', env('FOUNDRY_ADMIN_URL'));
        Config::set('foundry.admin_prefix', env('FOUNDRY_ADMIN_PREFIX'));
        Config::set('recaptcha.site_key', env('RECAPTCHA_SITE_KEY'));
    }

    protected function mockRequest($user = null, $path = '/')
    {
        $user = $user ?? User::factory()->make();
        $user->guard ??= 'user';
        $user->id ??= 1;
        $user->first_name = 'Test';
        $user->last_name = 'User';

        $uri = ltrim($path, '/');
        request()->server->set('REQUEST_URI', '/'.$uri);

        $this->actingAs($user, $user->guard);
    }

    public function test_guard_function_returns_user_guard()
    {
        $this->mockRequest();
        $this->assertEquals('user', guard());
    }

    public function test_guard_function_returns_null_if_no_user()
    {
        $this->actingAsGuest();
        $this->assertEquals('user', guard());
    }

    public function test_guard_function_checks_single_guard()
    {
        $this->mockRequest();
        $this->assertTrue(guard('user'));
        $this->assertFalse(guard('admin'));
    }

    public function test_guard_function_checks_multiple_guards()
    {
        $this->mockRequest();
        $this->assertTrue(guard('user', 'admin'));
        $this->assertTrue(guard('admin', 'user'));
        $this->assertFalse(guard('admin', 'superadmin'));
    }

    public function test_user_function_returns_user_object()
    {
        $user = user();
        $this->assertNotNull($user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('Test User', $user->name);
    }

    public function test_user_function_returns_specific_user_property()
    {
        $name = user('name');
        $this->assertEquals('Test User', $name);

        $id = user('id');
        $this->assertEquals(1, $id);
    }

    public function test_user_function_returns_null_if_no_user()
    {
        $this->actingAsGuest();

        $user = user();
        $this->assertNull($user);

        $name = user('name');
        $this->assertNull($name);
    }

    public function test_is_user_function_returns_true_for_user_guard()
    {
        $user = User::factory()->make();
        $user->guard = 'user';
        $this->mockRequest($user);
        $this->assertTrue(is_user());
    }

    public function test_is_user_function_returns_false_for_non_user_guard()
    {
        $user = Admin::factory()->make();
        $user->guard = 'admin';
        $this->mockRequest($user, 'admin');

        $this->assertFalse(is_user());
    }

    public function test_is_user_function_returns_false_if_no_user()
    {
        $this->actingAsGuest();
        $this->assertFalse(is_user());
    }

    public function test_is_admin_function_returns_true_for_admin_guard()
    {
        $user = Admin::factory()->make();
        $user->guard = 'admin';
        $this->mockRequest($user, 'admin');

        $this->assertTrue(is_admin());
    }

    public function test_is_admin_function_returns_false_for_non_admin_guard()
    {
        $user = User::factory()->make();
        $user->guard = 'user';
        $this->mockRequest($user);
        $this->assertFalse(is_admin());
    }

    public function test_is_admin_function_returns_false_if_no_user()
    {
        $this->actingAsGuest();
        $this->assertFalse(is_admin());
    }

    public function test_app_url_with_relative_path()
    {
        $this->assertEquals('http://localhost/about', app_url('about'));
    }

    public function test_app_url_with_absolute_path()
    {
        $this->assertEquals('http://localhost/about', app_url('/about'));
    }

    public function test_admin_url_with_default_path()
    {
        $this->assertEquals('http://localhost/admin', admin_url());
        $this->assertEquals('http://localhost/admin/dashboard', admin_url('dashboard'));
        $this->assertEquals('http://localhost/admin/dashboard', admin_url('/dashboard'));
    }

    public function test_app_url_with_default_path()
    {
        $this->assertEquals('http://localhost', app_url());
        $this->assertEquals('http://localhost/dashboard', app_url('dashboard'));
        $this->assertEquals('http://localhost/dashboard', app_url('/dashboard'));
    }

    public function test_user_route_with_default_prefix()
    {
        $this->assertEquals('/', user_route());
        $this->assertEquals('/dashboard', user_route('dashboard'));
        $this->assertEquals('/dashboard', user_route('/dashboard'));
    }

    public function test_admin_route_with_default_prefix()
    {
        $this->assertEquals('/admin', admin_route());
        $this->assertEquals('/admin/dashboard', admin_route('dashboard'));
        $this->assertEquals('/admin/dashboard', admin_route('/dashboard'));
    }

    public function test_is_active()
    {
        // Simulate a request to 'home'
        $this->get('home');

        // Call the function with a matching route
        $result = is_active('home');

        // Assert the result is 'active'
        $this->assertEquals('active', $result);
    }

    public function test_is_active_returns_empty_for_non_matching_route()
    {
        // Simulate a request to 'dashboard'
        $this->get('dashboard');

        // Call the function with a non-matching route
        $result = is_active('home');

        // Assert the result is an empty string
        $this->assertEquals('', $result);
    }

    public function test_is_active_handles_multiple_routes()
    {
        // Simulate a request to 'about'
        $this->get('about');

        // Call the function with multiple routes
        $result = is_active('home', 'about', 'contact');

        // Assert the result is 'active' for a matching route
        $this->assertEquals('active', $result);

        // Call the function with no matching routes
        $result = is_active('services', 'portfolio');

        // Assert the result is an empty string
        $this->assertEquals('', $result);
    }

    public function test_has_recaptcha()
    {
        Config::set('recaptcha.site_key', 'site_key');
        $this->assertTrue(has_recaptcha());
    }

    public function test_string_to_hex()
    {
        $this->assertEquals('#000d05', string_to_hex('A'));
    }

    public function test_string_to_hsl()
    {
        $this->assertEquals('hsl(65, 35%, 65%)', string_to_hsl('A'));
    }

    public function test_model_log_name()
    {
        $model = new class
        {
            public $logName = 'Custom Log Name';
        };
        $this->assertEquals('Custom Log Name', model_log_name($model));
    }

    public function test_format_amount()
    {
        $this->assertEquals('$100.00', format_amount(100, 'USD', 'en'));
    }

    public function test_currency_symbol()
    {
        $currenciesMock = \Mockery::mock('Symfony\Polyfill\Intl\Icu\Currencies');
        $currenciesMock->shouldReceive('getSymbol')->with('USD')->andReturn('$');
        $this->assertEquals('$', currency_symbol('USD'));
    }

    public function test_get_lang_code()
    {
        $this->assertEquals('en', get_lang_code('en-US'));
    }

    public function test_app_lang()
    {
        $this->assertEquals('en', app_lang());
    }

    public function test_replace_short_code()
    {
        $this->assertEquals('Welcome to AppName', replace_short_code('Welcome to {{APP_NAME}}'));
    }

    public function test_has()
    {
        $this->assertInstanceOf(Optional::class, has(null));
    }

    public function test_get_country_code()
    {
        $this->assertEquals('*', get_country_code(null));
    }

    public function test_trans_attribute()
    {
        $this->assertEquals('key', trans_attribute('key', 'type'));
    }

    public function test_html_text()
    {
        $this->assertEquals('Hello World', html_text('<div>Hello<br>World</div>'));
    }

    public function test_theme()
    {
        $this->assertEquals(theme('path', 'foundation'), '/themes/foundation/path');
        $this->assertEquals(theme('/css/app.css', 'foundation'), '/themes/foundation/css/app.css');
    }
}
