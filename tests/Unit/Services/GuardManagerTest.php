<?php

use Foundry\Facades\Guard;
use Foundry\Tests\BaseTestCase;
use Illuminate\Http\Request;

uses(BaseTestCase::class);

it('resolves default context from request', function () {
    $this->instance('request', Request::create('/admin'));
    $this->assertEquals('admin', Guard::current());

    Guard::forgetResolved();
    $this->instance('request', Request::create('/dashboard'));
    $this->assertEquals('user', Guard::current());
});

it('can set and get request', function () {
    $request = Request::create('/admin');
    $this->instance('request', $request);

    $this->assertSame($request, Guard::getRequest());
    $this->assertTrue(Guard::is('admin'));
});

it('set request clears resolved state', function () {
    $this->instance('request', Request::create('/admin'));
    $this->assertEquals('admin', Guard::current());

    Guard::forgetResolved();
    $this->instance('request', Request::create('/dashboard'));
    $this->assertEquals('user', Guard::current());
});

it('supports custom resolvers', function () {
    Guard::resolveUsing(fn () => 'custom');

    $this->assertEquals('custom', Guard::current());
    $this->assertTrue(Guard::is('custom'));
});

it('resolves values from config', function () {
    config(['foundry.guards.admin.home' => '/custom-admin-home']);

    $this->instance('request', Request::create('/admin'));
    $this->assertEquals('/custom-admin-home', Guard::home());
});

it('falls back to hardcoded defaults', function () {
    $this->instance('request', Request::create('/admin'));

    $this->assertEquals('/admin', Guard::home());
    $this->assertEquals('admin.login', Guard::loginRoute());
});

it('explicit request does not poison global state', function () {
    $globalRequest = Request::create('/dashboard');

    $this->assertEquals('user', Guard::current());

    $explicitRequest = Request::create('/admin');
    $this->assertEquals('admin', Guard::current($explicitRequest));

    $this->assertEquals('user', Guard::current());
});

it('forget resolved works', function () {
    $this->instance('request', Request::create('/admin'));
    Guard::current();

    Guard::forgetResolved();

    config(['foundry.admin_prefix' => 'portal']);

    $this->assertEquals('user', Guard::current());
});

it('aliases work', function () {
    $this->instance('request', Request::create('/admin'));

    $this->assertEquals('admin', Guard::current());
    $this->assertEquals('admin', Guard::key());
    $this->assertEquals('admin', Guard::context());
});
