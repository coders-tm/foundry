<?php

use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Redeem;
use Foundry\Models\Subscription;
use Foundry\Tests\BaseTestCase;
use Workbench\App\Models\Coupon;
use Workbench\App\Models\Plan;

uses(BaseTestCase::class);

it('coupon model can be configured', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);

    $this->assertEquals(Coupon::class, Foundry\Foundry::$couponModel);
});

it('subscription coupon relationship uses configured model', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);

    $subscription = new Subscription;

    $this->assertEquals(Coupon::class, $subscription->coupon()->getRelated()::class);
});

it('redeem coupon relationship uses configured model', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);

    $redeem = new Redeem;

    $this->assertEquals(Coupon::class, $redeem->coupon()->getRelated()::class);
});

it('discount line coupon relationship uses configured model', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);

    $discountLine = new DiscountLine;

    $this->assertEquals(Coupon::class, $discountLine->coupon()->getRelated()::class);
});

it('extended subscription can override can apply coupon without type error', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);

    $mockSubscription = new class extends Subscription
    {
        public function canApplyCoupon($coupon = null)
        {
            return parent::canApplyCoupon($coupon);
        }
    };

    $this->assertInstanceOf(Subscription::class, $mockSubscription);
});

it('workbench extended subscription model can be instantiated', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);
    Foundry\Foundry::useSubscriptionModel(Workbench\App\Models\Subscription::class);

    $subscription = new Workbench\App\Models\Subscription;

    $this->assertInstanceOf(Workbench\App\Models\Subscription::class, $subscription);
    $this->assertInstanceOf(Subscription::class, $subscription);

    $this->assertTrue(method_exists($subscription, 'canApplyCoupon'));
    $this->assertTrue(method_exists($subscription, 'hasSpecialCoupon'));
});

it('plan model can be configured', function () {
    Foundry\Foundry::usePlanModel(Plan::class);

    $this->assertEquals(Plan::class, Foundry\Foundry::$planModel);
});
