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
    expect(guard())->toBe('user');
});

it('guard function returns null if no user', function () {
    $this->actingAsGuest();
    expect(guard())->toBe('user');
});

it('guard function checks single guard', function () {
    mockRequest();
    expect(guard('user'))->toBeTrue();
    expect(guard('admin'))->toBeFalse();
});

it('guard function checks multiple guards', function () {
    mockRequest();
    expect(guard('user', 'admin'))->toBeTrue();
    expect(guard('admin', 'user'))->toBeTrue();
    expect(guard('admin', 'superadmin'))->toBeFalse();
});

it('user function returns user object', function () {
    $user = user();
    expect($user)->not->toBeNull();
    expect($user->id)->toBe(1);
    expect($user->name)->toBe('Test User');
});

it('user function returns specific user property', function () {
    $name = user('name');
    expect($name)->toBe('Test User');

    $id = user('id');
    expect($id)->toBe(1);
});

it('user function returns null if no user', function () {
    $this->actingAsGuest();

    $user = user();
    expect($user)->toBeNull();

    $name = user('name');
    expect($name)->toBeNull();
});

it('is user function returns true for user guard', function () {
    $user = User::factory()->make();
    $user->guard = 'user';
    mockRequest($user);
    expect(is_user())->toBeTrue();
});

it('is user function returns false for non user guard', function () {
    $user = Admin::factory()->make();
    $user->guard = 'admin';
    mockRequest($user, 'admin');

    expect(is_user())->toBeFalse();
});

it('is user function returns false if no user', function () {
    $this->actingAsGuest();
    expect(is_user())->toBeFalse();
});

it('is admin function returns true for admin guard', function () {
    $user = Admin::factory()->make();
    $user->guard = 'admin';
    mockRequest($user, 'admin');

    expect(is_admin())->toBeTrue();
});

it('is admin function returns false for non admin guard', function () {
    $user = User::factory()->make();
    $user->guard = 'user';
    mockRequest($user);
    expect(is_admin())->toBeFalse();
});

it('is admin function returns false if no user', function () {
    $this->actingAsGuest();
    expect(is_admin())->toBeFalse();
});

it('app url with relative path', function () {
    expect(app_url('about'))->toBe('http://localhost/about');
});

it('app url with absolute path', function () {
    expect(app_url('/about'))->toBe('http://localhost/about');
});

it('admin url with default path', function () {
    expect(admin_url())->toBe('http://localhost/admin');
    expect(admin_url('dashboard'))->toBe('http://localhost/admin/dashboard');
    expect(admin_url('/dashboard'))->toBe('http://localhost/admin/dashboard');
});

it('app url with default path', function () {
    expect(app_url())->toBe('http://localhost');
    expect(app_url('dashboard'))->toBe('http://localhost/dashboard');
    expect(app_url('/dashboard'))->toBe('http://localhost/dashboard');
});

it('user route with default prefix', function () {
    expect(user_route())->toBe('/');
    expect(user_route('dashboard'))->toBe('/dashboard');
    expect(user_route('/dashboard'))->toBe('/dashboard');
});

it('admin route with default prefix', function () {
    expect(admin_route())->toBe('/admin');
    expect(admin_route('dashboard'))->toBe('/admin/dashboard');
    expect(admin_route('/dashboard'))->toBe('/admin/dashboard');
});

it('is active', function () {
    $this->get('home');
    $result = is_active('home');
    expect($result)->toBe('active');
});

it('is active returns empty for non matching route', function () {
    $this->get('dashboard');
    $result = is_active('home');
    expect($result)->toBe('');
});

it('is active handles multiple routes', function () {
    $this->get('about');
    $result = is_active('home', 'about', 'contact');
    expect($result)->toBe('active');

    $result = is_active('services', 'portfolio');
    expect($result)->toBe('');
});

it('has recaptcha', function () {
    Config::set('recaptcha.site_key', 'site_key');
    expect(has_recaptcha())->toBeTrue();
});

it('string to hex', function () {
    expect(string_to_hex('A'))->toBe('#000d05');
});

it('string to hsl', function () {
    expect(string_to_hsl('A'))->toBe('hsl(65, 35%, 65%)');
});

it('model log name', function () {
    $model = new class
    {
        public $logName = 'Custom Log Name';
    };
    expect(model_log_name($model))->toBe('Custom Log Name');
});

it('format amount', function () {
    expect(format_amount(100, 'USD', 'en'))->toBe('$100.00');
});

it('currency symbol', function () {
    $currenciesMock = Mockery::mock('Symfony\Polyfill\Intl\Icu\Currencies');
    $currenciesMock->shouldReceive('getSymbol')->with('USD')->andReturn('$');
    expect(currency_symbol('USD'))->toBe('$');
});

it('get lang code', function () {
    expect(get_lang_code('en-US'))->toBe('en');
});

it('app lang', function () {
    expect(app_lang())->toBe('en');
});

it('replace short code', function () {
    expect(replace_short_code('Welcome to {{APP_NAME}}'))->toBe('Welcome to AppName');
});

it('has', function () {
    expect(has(null))->toBeInstanceOf(Optional::class);
});

it('get country code', function () {
    expect(BaseRepository::getCountryCode(null))->toBe('*');
});
