<?php

namespace Foundry\Tests\Unit\Services;

use Foundry\Facades\Guard;
use Foundry\Tests\BaseTestCase;
use Illuminate\Http\Request;

class GuardManagerTest extends BaseTestCase
{
    public function test_it_resolves_default_context_from_request()
    {
        $request = Request::create('/admin');
        $this->assertEquals('admin', Guard::current());

        $request = Request::create('/dashboard');
        $this->assertEquals('user', Guard::current());
    }

    public function test_it_can_set_and_get_request()
    {
        $request = Request::create('/admin');
        $this->assertSame($request, Guard::getRequest());
        $this->assertTrue(Guard::is('admin'));
    }

    public function test_set_request_clears_resolved_state()
    {
       Request::create('/admin');
        $this->assertEquals('admin', Guard::current());

        Request::create('/dashboard');
        $this->assertEquals('user', Guard::current());
    }

    public function test_it_supports_custom_resolvers()
    {
        Guard::resolveUsing(fn() => 'custom');

        $this->assertEquals('custom', Guard::current());
        $this->assertTrue(Guard::is('custom'));
    }

    public function test_it_resolves_values_from_config()
    {
        config(['foundry.guards.admin.home' => '/custom-admin-home']);

        Request::create('/admin');
        $this->assertEquals('/custom-admin-home', Guard::home());
    }

    public function test_it_falls_back_to_hardcoded_defaults()
    {
        Request::create('/admin');

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
        $request = Request::create('/admin');
        Guard::setRequest($request);
        Guard::current(); // Resolve it

        Guard::forgetResolved();

        // Manually change config to see if it re-resolves
        config(['foundry.guards.admin.paths' => ['something-else']]);

        $this->assertEquals('user', Guard::current()); // Now /admin doesn't match admin paths
    }

    public function test_aliases_work()
    {
        $request = Request::create('/admin');
        Guard::setRequest($request);

        $this->assertEquals('admin', Guard::current());
        $this->assertEquals('admin', Guard::key());
        $this->assertEquals('admin', Guard::context());
    }
}
