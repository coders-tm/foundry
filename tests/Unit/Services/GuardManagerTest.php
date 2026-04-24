<?php

namespace Foundry\Tests\Unit\Services;

use Foundry\Facades\Guard;
use Foundry\Tests\BaseTestCase;
use Illuminate\Http\Request;

class GuardManagerTest extends BaseTestCase
{
    public function test_it_resolves_default_context_from_request()
    {
        $this->instance('request', Request::create('/admin'));
        $this->assertEquals('admin', Guard::current());

        Guard::forgetResolved();
        $this->instance('request', Request::create('/dashboard'));
        $this->assertEquals('user', Guard::current());
    }

    public function test_it_can_set_and_get_request()
    {
        $request = Request::create('/admin');
        $this->instance('request', $request);

        $this->assertSame($request, Guard::getRequest());
        $this->assertTrue(Guard::is('admin'));
    }

    public function test_set_request_clears_resolved_state()
    {
        $this->instance('request', Request::create('/admin'));
        $this->assertEquals('admin', Guard::current());

        Guard::forgetResolved();
        $this->instance('request', Request::create('/dashboard'));
        $this->assertEquals('user', Guard::current());
    }

    public function test_it_supports_custom_resolvers()
    {
        Guard::resolveUsing(fn () => 'custom');

        $this->assertEquals('custom', Guard::current());
        $this->assertTrue(Guard::is('custom'));
    }

    public function test_it_resolves_values_from_config()
    {
        config(['foundry.guards.admin.home' => '/custom-admin-home']);

        $this->instance('request', Request::create('/admin'));
        $this->assertEquals('/custom-admin-home', Guard::home());
    }

    public function test_it_falls_back_to_hardcoded_defaults()
    {
        $this->instance('request', Request::create('/admin'));

        // These should come from defaultValue() match block
        $this->assertEquals('/admin', Guard::home());
        $this->assertEquals('admin.login', Guard::loginRoute());
    }

    public function test_explicit_request_does_not_poison_global_state()
    {
        $globalRequest = Request::create('/dashboard');

        $this->assertEquals('user', Guard::current());

        $explicitRequest = Request::create('/admin');
        $this->assertEquals('admin', Guard::current($explicitRequest));

        // Context should still be 'user' for the global state
        $this->assertEquals('user', Guard::current());
    }

    public function test_forget_resolved_works()
    {
        $this->instance('request', Request::create('/admin'));
        Guard::current(); // Resolve it ('admin')

        Guard::forgetResolved();

        // Change the prefix to something else so /admin no longer matches
        config(['foundry.admin_prefix' => 'portal']);

        $this->assertEquals('user', Guard::current());
    }

    public function test_aliases_work()
    {
        $this->instance('request', Request::create('/admin'));

        $this->assertEquals('admin', Guard::current());
        $this->assertEquals('admin', Guard::key());
        $this->assertEquals('admin', Guard::context());
    }
}
