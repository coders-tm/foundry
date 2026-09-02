<?php

use Foundry\Models\Coupon;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\SupportTicket;
use Foundry\Tests\BaseTestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Workbench\App\Models\Admin;
use Workbench\App\Models\User;

uses(BaseTestCase::class);

afterEach(function () {
    Foundry\Foundry::useUserModel('App\\Models\\User');
    Foundry\Foundry::useAdminModel('App\\Models\\Admin');
    Foundry\Foundry::useOrderModel(Order::class);
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    Foundry\Foundry::usePlanModel(Plan::class);
    Foundry\Foundry::useCouponModel(Coupon::class);
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);
});

it('use user model sets static property', function () {
    Foundry\Foundry::useUserModel(User::class);
    expect(Foundry\Foundry::$userModel)->toBe(User::class);
});

it('use user model also sets subscription user model', function () {
    Foundry\Foundry::useUserModel(User::class);
    expect(Foundry\Foundry::$subscriptionUserModel)->toBe(User::class);
});

it('use user model registers morph map', function () {
    Foundry\Foundry::useUserModel(User::class);
    $morphMap = Relation::morphMap();
    expect($morphMap)->toHaveKey('User');
    expect($morphMap['User'])->toBe(User::class);
});

it('use user model morph class matches map', function () {
    Foundry\Foundry::useUserModel(User::class);
    expect((new User)->getMorphClass())->toBe('User');
});

it('use admin model sets static property', function () {
    Foundry\Foundry::useAdminModel(Admin::class);
    expect(Foundry\Foundry::$adminModel)->toBe(Admin::class);
});

it('use admin model registers morph map', function () {
    Foundry\Foundry::useAdminModel(Admin::class);
    $morphMap = Relation::morphMap();
    expect($morphMap)->toHaveKey('Admin');
    expect($morphMap['Admin'])->toBe(Admin::class);
});

it('use admin model morph class matches map', function () {
    Foundry\Foundry::useAdminModel(Admin::class);
    expect((new Admin)->getMorphClass())->toBe('Admin');
});

it('use order model sets static property', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    expect(Foundry\Foundry::$orderModel)->toBe(Order::class);
});

it('use order model registers morph map', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    $morphMap = Relation::morphMap();
    expect($morphMap)->toHaveKey('Order');
    expect($morphMap['Order'])->toBe(Order::class);
});

it('use order model morph class matches map', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    expect((new Order)->getMorphClass())->toBe('Order');
});

it('use subscription model sets static property', function () {
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    expect(Foundry\Foundry::$subscriptionModel)->toBe(Subscription::class);
});

it('use subscription model registers morph map', function () {
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    $morphMap = Relation::morphMap();
    expect($morphMap)->toHaveKey('Subscription');
    expect($morphMap['Subscription'])->toBe(Subscription::class);
});

it('use subscription model morph class matches map', function () {
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    expect((new Subscription)->getMorphClass())->toBe('Subscription');
});

it('custom subscription model morph class matches map', function () {
    Foundry\Foundry::useSubscriptionModel(Workbench\App\Models\Subscription::class);
    expect((new Workbench\App\Models\Subscription)->getMorphClass())->toBe('Subscription');
});

it('use plan model sets static property', function () {
    Foundry\Foundry::usePlanModel(Plan::class);
    expect(Foundry\Foundry::$planModel)->toBe(Plan::class);
});

it('use plan model registers morph map', function () {
    Foundry\Foundry::usePlanModel(Plan::class);
    $morphMap = Relation::morphMap();
    expect($morphMap)->toHaveKey('Plan');
    expect($morphMap['Plan'])->toBe(Plan::class);
});

it('use plan model morph class matches map', function () {
    Foundry\Foundry::usePlanModel(Plan::class);
    expect((new Plan)->getMorphClass())->toBe('Plan');
});

it('custom plan model morph class matches map', function () {
    Foundry\Foundry::usePlanModel(Workbench\App\Models\Plan::class);
    expect((new Workbench\App\Models\Plan)->getMorphClass())->toBe('Plan');
});

it('use coupon model sets static property', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);
    expect(Foundry\Foundry::$couponModel)->toBe(Coupon::class);
});

it('use coupon model registers morph map', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);
    $morphMap = Relation::morphMap();
    expect($morphMap)->toHaveKey('Coupon');
    expect($morphMap['Coupon'])->toBe(Coupon::class);
});

it('use coupon model morph class matches map', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);
    expect((new Coupon)->getMorphClass())->toBe('Coupon');
});

it('custom coupon model morph class matches map', function () {
    Foundry\Foundry::useCouponModel(Workbench\App\Models\Coupon::class);
    expect((new Workbench\App\Models\Coupon)->getMorphClass())->toBe('Coupon');
});

it('use support ticket model sets static property', function () {
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);
    expect(Foundry\Foundry::$supportTicketModel)->toBe(SupportTicket::class);
});

it('use support ticket model registers morph map', function () {
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);
    $morphMap = Relation::morphMap();
    expect($morphMap)->toHaveKey('SupportTicket');
    expect($morphMap['SupportTicket'])->toBe(SupportTicket::class);
});

it('use support ticket model morph class matches map', function () {
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);
    expect((new SupportTicket)->getMorphClass())->toBe('SupportTicket');
});

it('use subscription user model sets static property', function () {
    Foundry\Foundry::useSubscriptionUserModel(User::class);
    expect(Foundry\Foundry::$subscriptionUserModel)->toBe(User::class);
});

it('use subscription user model does not alter user model', function () {
    $originalUserModel = Foundry\Foundry::$userModel;
    Foundry\Foundry::useSubscriptionUserModel(User::class);
    expect(Foundry\Foundry::$userModel)->toBe($originalUserModel);
});

it('all default morph map keys resolve to expected classes', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    Foundry\Foundry::usePlanModel(Plan::class);
    Foundry\Foundry::useCouponModel(Coupon::class);
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);

    $morphMap = Relation::morphMap();

    expect($morphMap['Order'])->toBe(Order::class);
    expect($morphMap['Subscription'])->toBe(Subscription::class);
    expect($morphMap['Plan'])->toBe(Plan::class);
    expect($morphMap['Coupon'])->toBe(Coupon::class);
    expect($morphMap['SupportTicket'])->toBe(SupportTicket::class);
});

it('all default models get morph class equal to morph map key', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    Foundry\Foundry::usePlanModel(Plan::class);
    Foundry\Foundry::useCouponModel(Coupon::class);
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);

    expect((new Order)->getMorphClass())->toBe('Order');
    expect((new Subscription)->getMorphClass())->toBe('Subscription');
    expect((new Plan)->getMorphClass())->toBe('Plan');
    expect((new Coupon)->getMorphClass())->toBe('Coupon');
    expect((new SupportTicket)->getMorphClass())->toBe('SupportTicket');
});
