<?php

use App\Models\User;
use Foundry\Facades\Settings;
use Foundry\Models\Admin;
use Foundry\Models\Tax;
use Foundry\Notifications\NewAdminNotification;
use Foundry\Repository\BaseRepository;
use Foundry\Tests\Feature\FeatureTestCase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

uses(FeatureTestCase::class);

beforeEach(function () {
    Route::get('/foo', function () {
        if (is_user()) {
            return guard();
        }

        return response(403);
    })->middleware('auth:user');

    Route::get('admin/email', function () {
        return user('email');
    })->middleware('auth:admin');
});

it('guard function returns user guard', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get('/foo')
        ->assertStatus(200)
        ->assertSee('user');
});

it('user function returns specific user property', function () {
    $user = Admin::factory()->create();

    $this->actingAs($user, 'admin');

    $this->get('/admin/email')
        ->assertStatus(200)
        ->assertSee($user->email);
});

it('settings', function () {
    Settings::set('foo', ['bar' => 'baz']);
    $this->assertEquals(['bar' => 'baz'], settings('foo'));
});

it('admin notify', function () {
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
});

it('country taxes', function () {
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
});

it('default tax', function () {
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
});

it('rest of world tax', function () {
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
});

it('billing address tax', function () {
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
});
