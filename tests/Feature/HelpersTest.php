<?php

uses(\Foundry\Tests\Feature\FeatureTestCase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Route::get('/foo', function () {
        if (is_user()) {
            return guard();
        }

        return response(403);
    })->middleware('auth:user');

    \Illuminate\Support\Facades\Route::get('admin/email', function () {
        return user('email');
    })->middleware('auth:admin');
});

it('guard function returns user guard', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user);

    $this->get('/foo')
        ->assertStatus(200)
        ->assertSee('user');
});

it('user function returns specific user property', function () {
    $user = \Foundry\Models\Admin::factory()->create();

    $this->actingAs($user, 'admin');

    $this->get('/admin/email')
        ->assertStatus(200)
        ->assertSee($user->email);
});

it('settings', function () {
    \Foundry\Facades\Settings::set('foo', ['bar' => 'baz']);
    $this->assertEquals(['bar' => 'baz'], settings('foo'));
});

it('admin notify', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $admin = \Foundry\Models\Admin::factory()->create();

    admin_notify(new \Foundry\Notifications\NewAdminNotification($admin, 'password'));

    \Illuminate\Support\Facades\Notification::assertSentTo(
        new \Illuminate\Notifications\AnonymousNotifiable,
        \Foundry\Notifications\NewAdminNotification::class,
        function ($notification, $channels) {
            return get_class($notification) === \Foundry\Notifications\NewAdminNotification::class;
        }
    );
});

it('country taxes', function () {
    $repository = new class extends \Foundry\Repository\BaseRepository {};

    \Foundry\Models\Tax::create([
        'country' => 'United States',
        'label' => 'VAT',
        'code' => 'US',
        'state' => '*',
        'rate' => 10,
        'priority' => 0,
    ]);

    \Foundry\Models\Tax::create([
        'country' => 'United States',
        'label' => 'VAT',
        'code' => 'US',
        'state' => 'California',
        'rate' => 15,
        'priority' => 1,
    ]);

    $this->assertNotEmpty($repository->countryTaxes('US'));
    $this->assertNotEmpty($repository->countryTaxes('US', 'California'));
});

it('default tax', function () {
    $repository = new class extends \Foundry\Repository\BaseRepository {};

    \Foundry\Models\Tax::create([
        'country' => 'United Kingdom',
        'label' => 'VAT',
        'code' => 'UK',
        'state' => '*',
        'rate' => 10,
        'priority' => 0,
    ]);

    \Foundry\Models\Tax::create([
        'country' => 'United Kingdom',
        'label' => 'VAT',
        'code' => 'UK',
        'state' => 'England',
        'rate' => 15,
        'priority' => 0,
    ]);

    $this->assertNotEmpty($repository->useDefaultTax()->tax_lines);
});

it('rest of world tax', function () {
    $repository = new class extends \Foundry\Repository\BaseRepository {};

    \Foundry\Models\Tax::create([
        'country' => 'Rest of World',
        'label' => 'VAT',
        'code' => '*',
        'state' => '*',
        'rate' => 10,
        'priority' => 0,
    ]);

    $this->assertNotEmpty($repository->restOfWorldTax());
});

it('billing address tax', function () {
    $repository = new class extends \Foundry\Repository\BaseRepository {};

    \Foundry\Models\Tax::create([
        'country' => 'Rest of World',
        'label' => 'VAT',
        'code' => '*',
        'state' => '*',
        'rate' => 10,
        'priority' => 0,
    ]);

    $this->assertNotEmpty($repository->getBillingAddressTax(['country' => 'United States']));
    $this->assertNotEmpty($repository->getBillingAddressTax(['country' => 'Canada']));
});
