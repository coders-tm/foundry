<?php

uses(Foundry\Tests\BaseTestCase::class);

afterEach(function () {
    \Foundry\Foundry::useUserModel('App\\Models\\User');
    \Foundry\Foundry::useAdminModel('App\\Models\\Admin');
    \Foundry\Foundry::useOrderModel(\Foundry\Models\Order::class);
    \Foundry\Foundry::useSubscriptionModel(\Foundry\Models\Subscription::class);
    \Foundry\Foundry::usePlanModel(\Foundry\Models\Subscription\Plan::class);
    \Foundry\Foundry::useCouponModel(\Foundry\Models\Coupon::class);
    \Foundry\Foundry::useSupportTicketModel(\Foundry\Models\SupportTicket::class);
});

it('use user model sets static property', function () {
    \Foundry\Foundry::useUserModel(\Workbench\App\Models\User::class);
    $this->assertSame(\Workbench\App\Models\User::class, \Foundry\Foundry::$userModel);
});

it('use user model also sets subscription user model', function () {
    \Foundry\Foundry::useUserModel(\Workbench\App\Models\User::class);
    $this->assertSame(\Workbench\App\Models\User::class, \Foundry\Foundry::$subscriptionUserModel);
});

it('use user model registers morph map', function () {
    \Foundry\Foundry::useUserModel(\Workbench\App\Models\User::class);
    $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
    $this->assertArrayHasKey('User', $morphMap);
    $this->assertSame(\Workbench\App\Models\User::class, $morphMap['User']);
});

it('use user model morph class matches map', function () {
    \Foundry\Foundry::useUserModel(\Workbench\App\Models\User::class);
    $this->assertSame('User', (new \Workbench\App\Models\User)->getMorphClass());
});

it('use admin model sets static property', function () {
    \Foundry\Foundry::useAdminModel(\Workbench\App\Models\Admin::class);
    $this->assertSame(\Workbench\App\Models\Admin::class, \Foundry\Foundry::$adminModel);
});

it('use admin model registers morph map', function () {
    \Foundry\Foundry::useAdminModel(\Workbench\App\Models\Admin::class);
    $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
    $this->assertArrayHasKey('Admin', $morphMap);
    $this->assertSame(\Workbench\App\Models\Admin::class, $morphMap['Admin']);
});

it('use admin model morph class matches map', function () {
    \Foundry\Foundry::useAdminModel(\Workbench\App\Models\Admin::class);
    $this->assertSame('Admin', (new \Workbench\App\Models\Admin)->getMorphClass());
});

it('use order model sets static property', function () {
    \Foundry\Foundry::useOrderModel(\Foundry\Models\Order::class);
    $this->assertSame(\Foundry\Models\Order::class, \Foundry\Foundry::$orderModel);
});

it('use order model registers morph map', function () {
    \Foundry\Foundry::useOrderModel(\Foundry\Models\Order::class);
    $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
    $this->assertArrayHasKey('Order', $morphMap);
    $this->assertSame(\Foundry\Models\Order::class, $morphMap['Order']);
});

it('use order model morph class matches map', function () {
    \Foundry\Foundry::useOrderModel(\Foundry\Models\Order::class);
    $this->assertSame('Order', (new \Foundry\Models\Order)->getMorphClass());
});

it('use subscription model sets static property', function () {
    \Foundry\Foundry::useSubscriptionModel(\Foundry\Models\Subscription::class);
    $this->assertSame(\Foundry\Models\Subscription::class, \Foundry\Foundry::$subscriptionModel);
});

it('use subscription model registers morph map', function () {
    \Foundry\Foundry::useSubscriptionModel(\Foundry\Models\Subscription::class);
    $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
    $this->assertArrayHasKey('Subscription', $morphMap);
    $this->assertSame(\Foundry\Models\Subscription::class, $morphMap['Subscription']);
});

it('use subscription model morph class matches map', function () {
    \Foundry\Foundry::useSubscriptionModel(\Foundry\Models\Subscription::class);
    $this->assertSame('Subscription', (new \Foundry\Models\Subscription)->getMorphClass());
});

it('custom subscription model morph class matches map', function () {
    \Foundry\Foundry::useSubscriptionModel(\Workbench\App\Models\Subscription::class);
    $this->assertSame('Subscription', (new \Workbench\App\Models\Subscription)->getMorphClass());
});

it('use plan model sets static property', function () {
    \Foundry\Foundry::usePlanModel(\Foundry\Models\Subscription\Plan::class);
    $this->assertSame(\Foundry\Models\Subscription\Plan::class, \Foundry\Foundry::$planModel);
});

it('use plan model registers morph map', function () {
    \Foundry\Foundry::usePlanModel(\Foundry\Models\Subscription\Plan::class);
    $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
    $this->assertArrayHasKey('Plan', $morphMap);
    $this->assertSame(\Foundry\Models\Subscription\Plan::class, $morphMap['Plan']);
});

it('use plan model morph class matches map', function () {
    \Foundry\Foundry::usePlanModel(\Foundry\Models\Subscription\Plan::class);
    $this->assertSame('Plan', (new \Foundry\Models\Subscription\Plan)->getMorphClass());
});

it('custom plan model morph class matches map', function () {
    \Foundry\Foundry::usePlanModel(\Workbench\App\Models\Plan::class);
    $this->assertSame('Plan', (new \Workbench\App\Models\Plan)->getMorphClass());
});

it('use coupon model sets static property', function () {
    \Foundry\Foundry::useCouponModel(\Foundry\Models\Coupon::class);
    $this->assertSame(\Foundry\Models\Coupon::class, \Foundry\Foundry::$couponModel);
});

it('use coupon model registers morph map', function () {
    \Foundry\Foundry::useCouponModel(\Foundry\Models\Coupon::class);
    $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
    $this->assertArrayHasKey('Coupon', $morphMap);
    $this->assertSame(\Foundry\Models\Coupon::class, $morphMap['Coupon']);
});

it('use coupon model morph class matches map', function () {
    \Foundry\Foundry::useCouponModel(\Foundry\Models\Coupon::class);
    $this->assertSame('Coupon', (new \Foundry\Models\Coupon)->getMorphClass());
});

it('custom coupon model morph class matches map', function () {
    \Foundry\Foundry::useCouponModel(\Workbench\App\Models\Coupon::class);
    $this->assertSame('Coupon', (new \Workbench\App\Models\Coupon)->getMorphClass());
});

it('use support ticket model sets static property', function () {
    \Foundry\Foundry::useSupportTicketModel(\Foundry\Models\SupportTicket::class);
    $this->assertSame(\Foundry\Models\SupportTicket::class, \Foundry\Foundry::$supportTicketModel);
});

it('use support ticket model registers morph map', function () {
    \Foundry\Foundry::useSupportTicketModel(\Foundry\Models\SupportTicket::class);
    $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
    $this->assertArrayHasKey('SupportTicket', $morphMap);
    $this->assertSame(\Foundry\Models\SupportTicket::class, $morphMap['SupportTicket']);
});

it('use support ticket model morph class matches map', function () {
    \Foundry\Foundry::useSupportTicketModel(\Foundry\Models\SupportTicket::class);
    $this->assertSame('SupportTicket', (new \Foundry\Models\SupportTicket)->getMorphClass());
});

it('use subscription user model sets static property', function () {
    \Foundry\Foundry::useSubscriptionUserModel(\Workbench\App\Models\User::class);
    $this->assertSame(\Workbench\App\Models\User::class, \Foundry\Foundry::$subscriptionUserModel);
});

it('use subscription user model does not alter user model', function () {
    $originalUserModel = \Foundry\Foundry::$userModel;
    \Foundry\Foundry::useSubscriptionUserModel(\Workbench\App\Models\User::class);
    $this->assertSame($originalUserModel, \Foundry\Foundry::$userModel);
});

it('all default morph map keys resolve to expected classes', function () {
    \Foundry\Foundry::useOrderModel(\Foundry\Models\Order::class);
    \Foundry\Foundry::useSubscriptionModel(\Foundry\Models\Subscription::class);
    \Foundry\Foundry::usePlanModel(\Foundry\Models\Subscription\Plan::class);
    \Foundry\Foundry::useCouponModel(\Foundry\Models\Coupon::class);
    \Foundry\Foundry::useSupportTicketModel(\Foundry\Models\SupportTicket::class);

    $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();

    $this->assertSame(\Foundry\Models\Order::class, $morphMap['Order']);
    $this->assertSame(\Foundry\Models\Subscription::class, $morphMap['Subscription']);
    $this->assertSame(\Foundry\Models\Subscription\Plan::class, $morphMap['Plan']);
    $this->assertSame(\Foundry\Models\Coupon::class, $morphMap['Coupon']);
    $this->assertSame(\Foundry\Models\SupportTicket::class, $morphMap['SupportTicket']);
});

it('all default models get morph class equal to morph map key', function () {
    \Foundry\Foundry::useOrderModel(\Foundry\Models\Order::class);
    \Foundry\Foundry::useSubscriptionModel(\Foundry\Models\Subscription::class);
    \Foundry\Foundry::usePlanModel(\Foundry\Models\Subscription\Plan::class);
    \Foundry\Foundry::useCouponModel(\Foundry\Models\Coupon::class);
    \Foundry\Foundry::useSupportTicketModel(\Foundry\Models\SupportTicket::class);

    $this->assertSame('Order', (new \Foundry\Models\Order)->getMorphClass());
    $this->assertSame('Subscription', (new \Foundry\Models\Subscription)->getMorphClass());
    $this->assertSame('Plan', (new \Foundry\Models\Subscription\Plan)->getMorphClass());
    $this->assertSame('Coupon', (new \Foundry\Models\Coupon)->getMorphClass());
    $this->assertSame('SupportTicket', (new \Foundry\Models\SupportTicket)->getMorphClass());
});
