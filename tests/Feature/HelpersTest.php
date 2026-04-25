<?php

namespace Foundry\Tests\Feature;

use App\Models\User;
use Foundry\Models\Admin;
use Foundry\Models\Setting;
use Foundry\Models\Tax;
use Foundry\Repository\BaseRepository;
use Foundry\Notifications\NewAdminNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

class HelpersTest extends FeatureTestCase
{
    protected function defineRoutes($router)
    {
        $router->get('/foo', function () {
            if (is_user()) {
                return guard();
            }

            return response(403);
        })->middleware('auth:user');

        $router->get('admin/email', function () {
            return user('email');
        })->middleware('auth:admin');
    }

    public function test_guard_function_returns_user_guard()
    {
        // Create the user
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);

        // Make the request to the route
        $this->get('/foo')
            ->assertStatus(200)
            ->assertSee('user');
    }

    public function test_user_function_returns_specific_user_property()
    {
        // Create the user
        /** @var Admin $user */
        $user = Admin::factory()->create();

        $this->actingAs($user, 'admin');

        // Make the request to the route
        $this->get('/admin/email')
            ->assertStatus(200)
            ->assertSee($user->email);
    }

    public function test_settings()
    {
        $settings = Setting::updateValue('foo', ['bar' => 'baz']);

        $this->assertEquals($settings, settings('foo'));
    }

    public function test_admin_notify()
    {
        Notification::fake();

        $admin = Admin::factory()->create();

        admin_notify(new NewAdminNotification($admin, 'password'));

        Notification::assertSentTo(
            new AnonymousNotifiable,
            NewAdminNotification::class,
            function ($notification, $channels) {
                return get_class($notification) === NewAdminNotification::class;
            }
        );
    }

    public function test_country_taxes()
    {
        $repository = new class extends BaseRepository {};

        Tax::create([
            'country' => 'United States',
            'label' => 'VAT',
            'code' => 'US',
            'state' => '*',
            'rate' => 10,
            'priority' => 0,
        ]);

        Tax::create([
            'country' => 'United States',
            'label' => 'VAT',
            'code' => 'US',
            'state' => 'California',
            'rate' => 15,
            'priority' => 1,
        ]);

        $this->assertNotEmpty($repository->countryTaxes('US'));
        $this->assertNotEmpty($repository->countryTaxes('US', 'California'));
    }

    public function test_default_tax()
    {
        $repository = new class extends BaseRepository {};

        Tax::create([
            'country' => 'United Kingdom',
            'label' => 'VAT',
            'code' => 'UK',
            'state' => '*',
            'rate' => 10,
            'priority' => 0,
        ]);

        Tax::create([
            'country' => 'United Kingdom',
            'label' => 'VAT',
            'code' => 'UK',
            'state' => 'England',
            'rate' => 15,
            'priority' => 0,
        ]);

        $this->assertNotEmpty($repository->useDefaultTax()->tax_lines);
    }

    public function test_rest_of_world_tax()
    {
        $repository = new class extends BaseRepository {};

        Tax::create([
            'country' => 'Rest of World',
            'label' => 'VAT',
            'code' => '*',
            'state' => '*',
            'rate' => 10,
            'priority' => 0,
        ]);

        $this->assertNotEmpty($repository->restOfWorldTax());
    }

    public function test_billing_address_tax()
    {
        $repository = new class extends BaseRepository {};

        Tax::create([
            'country' => 'Rest of World',
            'label' => 'VAT',
            'code' => '*',
            'state' => '*',
            'rate' => 10,
            'priority' => 0,
        ]);

        $this->assertNotEmpty($repository->getBillingAddressTax(['country' => 'United States']));
        $this->assertNotEmpty($repository->getBillingAddressTax(['country' => 'Canada']));
    }
}
