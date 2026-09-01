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
    $this->assertSame(User::class, Foundry\Foundry::$userModel);
});

it('use user model also sets subscription user model', function () {
    Foundry\Foundry::useUserModel(User::class);
    $this->assertSame(User::class, Foundry\Foundry::$subscriptionUserModel);
});

it('use user model registers morph map', function () {
    Foundry\Foundry::useUserModel(User::class);
    $morphMap = Relation::morphMap();
    $this->assertArrayHasKey('User', $morphMap);
    $this->assertSame(User::class, $morphMap['User']);
});

it('use user model morph class matches map', function () {
    Foundry\Foundry::useUserModel(User::class);
    $this->assertSame('User', (new User)->getMorphClass());
});

it('use admin model sets static property', function () {
    Foundry\Foundry::useAdminModel(Admin::class);
    $this->assertSame(Admin::class, Foundry\Foundry::$adminModel);
});

it('use admin model registers morph map', function () {
    Foundry\Foundry::useAdminModel(Admin::class);
    $morphMap = Relation::morphMap();
    $this->assertArrayHasKey('Admin', $morphMap);
    $this->assertSame(Admin::class, $morphMap['Admin']);
});

it('use admin model morph class matches map', function () {
    Foundry\Foundry::useAdminModel(Admin::class);
    $this->assertSame('Admin', (new Admin)->getMorphClass());
});

it('use order model sets static property', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    $this->assertSame(Order::class, Foundry\Foundry::$orderModel);
});

it('use order model registers morph map', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    $morphMap = Relation::morphMap();
    $this->assertArrayHasKey('Order', $morphMap);
    $this->assertSame(Order::class, $morphMap['Order']);
});

it('use order model morph class matches map', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    $this->assertSame('Order', (new Order)->getMorphClass());
});

it('use subscription model sets static property', function () {
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    $this->assertSame(Subscription::class, Foundry\Foundry::$subscriptionModel);
});

it('use subscription model registers morph map', function () {
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    $morphMap = Relation::morphMap();
    $this->assertArrayHasKey('Subscription', $morphMap);
    $this->assertSame(Subscription::class, $morphMap['Subscription']);
});

it('use subscription model morph class matches map', function () {
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    $this->assertSame('Subscription', (new Subscription)->getMorphClass());
});

it('custom subscription model morph class matches map', function () {
    Foundry\Foundry::useSubscriptionModel(Workbench\App\Models\Subscription::class);
    $this->assertSame('Subscription', (new Workbench\App\Models\Subscription)->getMorphClass());
});

it('use plan model sets static property', function () {
    Foundry\Foundry::usePlanModel(Plan::class);
    $this->assertSame(Plan::class, Foundry\Foundry::$planModel);
});

it('use plan model registers morph map', function () {
    Foundry\Foundry::usePlanModel(Plan::class);
    $morphMap = Relation::morphMap();
    $this->assertArrayHasKey('Plan', $morphMap);
    $this->assertSame(Plan::class, $morphMap['Plan']);
});

it('use plan model morph class matches map', function () {
    Foundry\Foundry::usePlanModel(Plan::class);
    $this->assertSame('Plan', (new Plan)->getMorphClass());
});

it('custom plan model morph class matches map', function () {
    Foundry\Foundry::usePlanModel(Workbench\App\Models\Plan::class);
    $this->assertSame('Plan', (new Workbench\App\Models\Plan)->getMorphClass());
});

it('use coupon model sets static property', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);
    $this->assertSame(Coupon::class, Foundry\Foundry::$couponModel);
});

it('use coupon model registers morph map', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);
    $morphMap = Relation::morphMap();
    $this->assertArrayHasKey('Coupon', $morphMap);
    $this->assertSame(Coupon::class, $morphMap['Coupon']);
});

it('use coupon model morph class matches map', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);
    $this->assertSame('Coupon', (new Coupon)->getMorphClass());
});

it('custom coupon model morph class matches map', function () {
    Foundry\Foundry::useCouponModel(Workbench\App\Models\Coupon::class);
    $this->assertSame('Coupon', (new Workbench\App\Models\Coupon)->getMorphClass());
});

it('use support ticket model sets static property', function () {
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);
    $this->assertSame(SupportTicket::class, Foundry\Foundry::$supportTicketModel);
});

it('use support ticket model registers morph map', function () {
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);
    $morphMap = Relation::morphMap();
    $this->assertArrayHasKey('SupportTicket', $morphMap);
    $this->assertSame(SupportTicket::class, $morphMap['SupportTicket']);
});

it('use support ticket model morph class matches map', function () {
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);
    $this->assertSame('SupportTicket', (new SupportTicket)->getMorphClass());
});

it('use subscription user model sets static property', function () {
    Foundry\Foundry::useSubscriptionUserModel(User::class);
    $this->assertSame(User::class, Foundry\Foundry::$subscriptionUserModel);
});

it('use subscription user model does not alter user model', function () {
    $originalUserModel = Foundry\Foundry::$userModel;
    Foundry\Foundry::useSubscriptionUserModel(User::class);
    $this->assertSame($originalUserModel, Foundry\Foundry::$userModel);
});

it('all default morph map keys resolve to expected classes', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    Foundry\Foundry::usePlanModel(Plan::class);
    Foundry\Foundry::useCouponModel(Coupon::class);
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);

    $morphMap = Relation::morphMap();

    $this->assertSame(Order::class, $morphMap['Order']);
    $this->assertSame(Subscription::class, $morphMap['Subscription']);
    $this->assertSame(Plan::class, $morphMap['Plan']);
    $this->assertSame(Coupon::class, $morphMap['Coupon']);
    $this->assertSame(SupportTicket::class, $morphMap['SupportTicket']);
});

it('all default models get morph class equal to morph map key', function () {
    Foundry\Foundry::useOrderModel(Order::class);
    Foundry\Foundry::useSubscriptionModel(Subscription::class);
    Foundry\Foundry::usePlanModel(Plan::class);
    Foundry\Foundry::useCouponModel(Coupon::class);
    Foundry\Foundry::useSupportTicketModel(SupportTicket::class);

    $this->assertSame('Order', (new Order)->getMorphClass());
    $this->assertSame('Subscription', (new Subscription)->getMorphClass());
    $this->assertSame('Plan', (new Plan)->getMorphClass());
    $this->assertSame('Coupon', (new Coupon)->getMorphClass());
    $this->assertSame('SupportTicket', (new SupportTicket)->getMorphClass());
});
