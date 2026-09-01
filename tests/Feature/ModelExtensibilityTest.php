<?php

uses(Foundry\Tests\BaseTestCase::class);

it('coupon model can be configured', function () {
    \Foundry\Foundry::useCouponModel(\Workbench\App\Models\Coupon::class);

    $this->assertEquals(\Workbench\App\Models\Coupon::class, \Foundry\Foundry::$couponModel);
});

it('subscription coupon relationship uses configured model', function () {
    \Foundry\Foundry::useCouponModel(\Workbench\App\Models\Coupon::class);

    $subscription = new \Foundry\Models\Subscription;

    $this->assertEquals(\Workbench\App\Models\Coupon::class, $subscription->coupon()->getRelated()::class);
});

it('redeem coupon relationship uses configured model', function () {
    \Foundry\Foundry::useCouponModel(\Workbench\App\Models\Coupon::class);

    $redeem = new \Foundry\Models\Redeem;

    $this->assertEquals(\Workbench\App\Models\Coupon::class, $redeem->coupon()->getRelated()::class);
});

it('discount line coupon relationship uses configured model', function () {
    \Foundry\Foundry::useCouponModel(\Workbench\App\Models\Coupon::class);

    $discountLine = new \Foundry\Models\Order\DiscountLine;

    $this->assertEquals(\Workbench\App\Models\Coupon::class, $discountLine->coupon()->getRelated()::class);
});

it('extended subscription can override can apply coupon without type error', function () {
    \Foundry\Foundry::useCouponModel(\Workbench\App\Models\Coupon::class);

    $mockSubscription = new class extends \Foundry\Models\Subscription
    {
        public function canApplyCoupon($coupon = null)
        {
            return parent::canApplyCoupon($coupon);
        }
    };

    $this->assertInstanceOf(\Foundry\Models\Subscription::class, $mockSubscription);
});

it('workbench extended subscription model can be instantiated', function () {
    \Foundry\Foundry::useCouponModel(\Workbench\App\Models\Coupon::class);
    \Foundry\Foundry::useSubscriptionModel(\Workbench\App\Models\Subscription::class);

    $subscription = new \Workbench\App\Models\Subscription;

    $this->assertInstanceOf(\Workbench\App\Models\Subscription::class, $subscription);
    $this->assertInstanceOf(\Foundry\Models\Subscription::class, $subscription);

    $this->assertTrue(method_exists($subscription, 'canApplyCoupon'));
    $this->assertTrue(method_exists($subscription, 'hasSpecialCoupon'));
});

it('plan model can be configured', function () {
    \Foundry\Foundry::usePlanModel(\Workbench\App\Models\Plan::class);

    $this->assertEquals(\Workbench\App\Models\Plan::class, \Foundry\Foundry::$planModel);
});
