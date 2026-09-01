<?php

uses(Foundry\Tests\BaseTestCase::class);

it('resolves default context from request', function () {
    $this->instance('request', \Illuminate\Http\Request::create('/admin'));
    $this->assertEquals('admin', \Foundry\Facades\Guard::current());

    \Foundry\Facades\Guard::forgetResolved();
    $this->instance('request', \Illuminate\Http\Request::create('/dashboard'));
    $this->assertEquals('user', \Foundry\Facades\Guard::current());
});

it('can set and get request', function () {
    $request = \Illuminate\Http\Request::create('/admin');
    $this->instance('request', $request);

    $this->assertSame($request, \Foundry\Facades\Guard::getRequest());
    $this->assertTrue(\Foundry\Facades\Guard::is('admin'));
});

it('set request clears resolved state', function () {
    $this->instance('request', \Illuminate\Http\Request::create('/admin'));
    $this->assertEquals('admin', \Foundry\Facades\Guard::current());

    \Foundry\Facades\Guard::forgetResolved();
    $this->instance('request', \Illuminate\Http\Request::create('/dashboard'));
    $this->assertEquals('user', \Foundry\Facades\Guard::current());
});

it('supports custom resolvers', function () {
    \Foundry\Facades\Guard::resolveUsing(fn () => 'custom');

    $this->assertEquals('custom', \Foundry\Facades\Guard::current());
    $this->assertTrue(\Foundry\Facades\Guard::is('custom'));
});

it('resolves values from config', function () {
    config(['foundry.guards.admin.home' => '/custom-admin-home']);

    $this->instance('request', \Illuminate\Http\Request::create('/admin'));
    $this->assertEquals('/custom-admin-home', \Foundry\Facades\Guard::home());
});

it('falls back to hardcoded defaults', function () {
    $this->instance('request', \Illuminate\Http\Request::create('/admin'));

    $this->assertEquals('/admin', \Foundry\Facades\Guard::home());
    $this->assertEquals('admin.login', \Foundry\Facades\Guard::loginRoute());
});

it('explicit request does not poison global state', function () {
    $globalRequest = \Illuminate\Http\Request::create('/dashboard');

    $this->assertEquals('user', \Foundry\Facades\Guard::current());

    $explicitRequest = \Illuminate\Http\Request::create('/admin');
    $this->assertEquals('admin', \Foundry\Facades\Guard::current($explicitRequest));

    $this->assertEquals('user', \Foundry\Facades\Guard::current());
});

it('forget resolved works', function () {
    $this->instance('request', \Illuminate\Http\Request::create('/admin'));
    \Foundry\Facades\Guard::current();

    \Foundry\Facades\Guard::forgetResolved();

    config(['foundry.admin_prefix' => 'portal']);

    $this->assertEquals('user', \Foundry\Facades\Guard::current());
});

it('aliases work', function () {
    $this->instance('request', \Illuminate\Http\Request::create('/admin'));

    $this->assertEquals('admin', \Foundry\Facades\Guard::current());
    $this->assertEquals('admin', \Foundry\Facades\Guard::key());
    $this->assertEquals('admin', \Foundry\Facades\Guard::context());
});
