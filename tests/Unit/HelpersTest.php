<?php

use App\Models\User;
use Foundry\Models\Admin;
use Foundry\Repository\BaseRepository;
use Foundry\Tests\BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Optional;

uses(BaseTestCase::class);

beforeEach(function () {
    Config::set('app.name', 'AppName');
    Config::set('app.url', env('APP_URL', 'http://localhost'));
    Config::set('foundry.admin_url', env('FOUNDRY_ADMIN_URL', 'http://localhost/admin'));
    Config::set('foundry.admin_prefix', env('FOUNDRY_ADMIN_PREFIX', 'admin'));
    Config::set('recaptcha.site_key', env('RECAPTCHA_SITE_KEY'));

    $user = User::factory()->make();
    $user->guard ??= 'user';
    $user->id ??= 1;
    $user->first_name = 'Test';
    $user->last_name = 'User';

    request()->server->set('REQUEST_URI', '/');

    $this->actingAs($user, $user->guard);
});

afterEach(function () {
    Config::set('app.name', 'AppName');
    Config::set('app.url', env('APP_URL', 'http://localhost'));
    Config::set('foundry.admin_url', env('FOUNDRY_ADMIN_URL', 'http://localhost/admin'));
    Config::set('foundry.admin_prefix', env('FOUNDRY_ADMIN_PREFIX', 'admin'));
    Config::set('recaptcha.site_key', env('RECAPTCHA_SITE_KEY'));
});

function mockRequest($user = null, $path = '/')
{
    $user = $user ?? User::factory()->make();
    $user->guard ??= 'user';
    $user->id ??= 1;
    $user->first_name = 'Test';
    $user->last_name = 'User';

    $uri = ltrim($path, '/');
    request()->server->set('REQUEST_URI', '/'.$uri);

    test()->actingAs($user, $user->guard);
}

it('guard function returns user guard', function () {
    mockRequest();
    $this->assertEquals('user', guard());
});

it('guard function returns null if no user', function () {
    $this->actingAsGuest();
    $this->assertEquals('user', guard());
});

it('guard function checks single guard', function () {
    mockRequest();
    $this->assertTrue(guard('user'));
    $this->assertFalse(guard('admin'));
});

it('guard function checks multiple guards', function () {
    mockRequest();
    $this->assertTrue(guard('user', 'admin'));
    $this->assertTrue(guard('admin', 'user'));
    $this->assertFalse(guard('admin', 'superadmin'));
});

it('user function returns user object', function () {
    $user = user();
    $this->assertNotNull($user);
    $this->assertEquals(1, $user->id);
    $this->assertEquals('Test User', $user->name);
});

it('user function returns specific user property', function () {
    $name = user('name');
    $this->assertEquals('Test User', $name);

    $id = user('id');
    $this->assertEquals(1, $id);
});

it('user function returns null if no user', function () {
    $this->actingAsGuest();

    $user = user();
    $this->assertNull($user);

    $name = user('name');
    $this->assertNull($name);
});

it('is user function returns true for user guard', function () {
    $user = User::factory()->make();
    $user->guard = 'user';
    mockRequest($user);
    $this->assertTrue(is_user());
});

it('is user function returns false for non user guard', function () {
    $user = Admin::factory()->make();
    $user->guard = 'admin';
    mockRequest($user, 'admin');

    $this->assertFalse(is_user());
});

it('is user function returns false if no user', function () {
    $this->actingAsGuest();
    $this->assertFalse(is_user());
});

it('is admin function returns true for admin guard', function () {
    $user = Admin::factory()->make();
    $user->guard = 'admin';
    mockRequest($user, 'admin');

    $this->assertTrue(is_admin());
});

it('is admin function returns false for non admin guard', function () {
    $user = User::factory()->make();
    $user->guard = 'user';
    mockRequest($user);
    $this->assertFalse(is_admin());
});

it('is admin function returns false if no user', function () {
    $this->actingAsGuest();
    $this->assertFalse(is_admin());
});

it('app url with relative path', function () {
    $this->assertEquals('http://localhost/about', app_url('about'));
});

it('app url with absolute path', function () {
    $this->assertEquals('http://localhost/about', app_url('/about'));
});

it('admin url with default path', function () {
    $this->assertEquals('http://localhost/admin', admin_url());
    $this->assertEquals('http://localhost/admin/dashboard', admin_url('dashboard'));
    $this->assertEquals('http://localhost/admin/dashboard', admin_url('/dashboard'));
});

it('app url with default path', function () {
    $this->assertEquals('http://localhost', app_url());
    $this->assertEquals('http://localhost/dashboard', app_url('dashboard'));
    $this->assertEquals('http://localhost/dashboard', app_url('/dashboard'));
});

it('user route with default prefix', function () {
    $this->assertEquals('/', user_route());
    $this->assertEquals('/dashboard', user_route('dashboard'));
    $this->assertEquals('/dashboard', user_route('/dashboard'));
});

it('admin route with default prefix', function () {
    $this->assertEquals('/admin', admin_route());
    $this->assertEquals('/admin/dashboard', admin_route('dashboard'));
    $this->assertEquals('/admin/dashboard', admin_route('/dashboard'));
});

it('is active', function () {
    $this->get('home');
    $result = is_active('home');
    $this->assertEquals('active', $result);
});

it('is active returns empty for non matching route', function () {
    $this->get('dashboard');
    $result = is_active('home');
    $this->assertEquals('', $result);
});

it('is active handles multiple routes', function () {
    $this->get('about');
    $result = is_active('home', 'about', 'contact');
    $this->assertEquals('active', $result);

    $result = is_active('services', 'portfolio');
    $this->assertEquals('', $result);
});

it('has recaptcha', function () {
    Config::set('recaptcha.site_key', 'site_key');
    $this->assertTrue(has_recaptcha());
});

it('string to hex', function () {
    $this->assertEquals('#000d05', string_to_hex('A'));
});

it('string to hsl', function () {
    $this->assertEquals('hsl(65, 35%, 65%)', string_to_hsl('A'));
});

it('model log name', function () {
    $model = new class
    {
        public $logName = 'Custom Log Name';
    };
    $this->assertEquals('Custom Log Name', model_log_name($model));
});

it('format amount', function () {
    $this->assertEquals('$100.00', format_amount(100, 'USD', 'en'));
});

it('currency symbol', function () {
    $currenciesMock = Mockery::mock('Symfony\Polyfill\Intl\Icu\Currencies');
    $currenciesMock->shouldReceive('getSymbol')->with('USD')->andReturn('$');
    $this->assertEquals('$', currency_symbol('USD'));
});

it('get lang code', function () {
    $this->assertEquals('en', get_lang_code('en-US'));
});

it('app lang', function () {
    $this->assertEquals('en', app_lang());
});

it('replace short code', function () {
    $this->assertEquals('Welcome to AppName', replace_short_code('Welcome to {{APP_NAME}}'));
});

it('has', function () {
    $this->assertInstanceOf(Optional::class, has(null));
});

it('get country code', function () {
    $this->assertEquals('*', BaseRepository::getCountryCode(null));
});
