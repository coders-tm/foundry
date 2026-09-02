<?php

use Foundry\Facades\Guard;
use Foundry\Tests\BaseTestCase;
use Illuminate\Http\Request;

uses(BaseTestCase::class);

it('resolves default context from request', function () {
    $this->instance('request', Request::create('/admin'));
    expect(Guard::current())->toBe('admin');

    Guard::forgetResolved();
    $this->instance('request', Request::create('/dashboard'));
    expect(Guard::current())->toBe('user');
});

it('can set and get request', function () {
    $request = Request::create('/admin');
    $this->instance('request', $request);

    expect(Guard::getRequest())->toBe($request);
    expect(Guard::is('admin'))->toBeTrue();
});

it('set request clears resolved state', function () {
    $this->instance('request', Request::create('/admin'));
    expect(Guard::current())->toBe('admin');

    Guard::forgetResolved();
    $this->instance('request', Request::create('/dashboard'));
    expect(Guard::current())->toBe('user');
});

it('supports custom resolvers', function () {
    Guard::resolveUsing(fn () => 'custom');

    expect(Guard::current())->toBe('custom');
    expect(Guard::is('custom'))->toBeTrue();
});

it('resolves values from config', function () {
    config(['foundry.guards.admin.home' => '/custom-admin-home']);

    $this->instance('request', Request::create('/admin'));
    expect(Guard::home())->toBe('/custom-admin-home');
});

it('falls back to hardcoded defaults', function () {
    $this->instance('request', Request::create('/admin'));

    expect(Guard::home())->toBe('/admin');
    expect(Guard::loginRoute())->toBe('admin.login');
});

it('explicit request does not poison global state', function () {
    $globalRequest = Request::create('/dashboard');

    expect(Guard::current())->toBe('user');

    $explicitRequest = Request::create('/admin');
    expect(Guard::current($explicitRequest))->toBe('admin');

    expect(Guard::current())->toBe('user');
});

it('forget resolved works', function () {
    $this->instance('request', Request::create('/admin'));
    Guard::current();

    Guard::forgetResolved();

    config(['foundry.admin_prefix' => 'portal']);

    expect(Guard::current())->toBe('user');
});

it('aliases work', function () {
    $this->instance('request', Request::create('/admin'));

    expect(Guard::current())->toBe('admin');
    expect(Guard::key())->toBe('admin');
    expect(Guard::context())->toBe('admin');
});
