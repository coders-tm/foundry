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

    expect(Foundry\Foundry::$couponModel)->toBe(Coupon::class);
});

it('subscription coupon relationship uses configured model', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);

    $subscription = new Subscription;

    expect($subscription->coupon()->getRelated()::class)->toBe(Coupon::class);
});

it('redeem coupon relationship uses configured model', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);

    $redeem = new Redeem;

    expect($redeem->coupon()->getRelated()::class)->toBe(Coupon::class);
});

it('discount line coupon relationship uses configured model', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);

    $discountLine = new DiscountLine;

    expect($discountLine->coupon()->getRelated()::class)->toBe(Coupon::class);
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

    expect($mockSubscription)->toBeInstanceOf(Subscription::class);
});

it('workbench extended subscription model can be instantiated', function () {
    Foundry\Foundry::useCouponModel(Coupon::class);
    Foundry\Foundry::useSubscriptionModel(Workbench\App\Models\Subscription::class);

    $subscription = new Workbench\App\Models\Subscription;

    expect($subscription)->toBeInstanceOf(Workbench\App\Models\Subscription::class);
    expect($subscription)->toBeInstanceOf(Subscription::class);

    expect(method_exists($subscription, 'canApplyCoupon'))->toBeTrue();
    expect(method_exists($subscription, 'hasSpecialCoupon'))->toBeTrue();
});

it('plan model can be configured', function () {
    Foundry\Foundry::usePlanModel(Plan::class);

    expect(Foundry\Foundry::$planModel)->toBe(Plan::class);
});

afterEach(function () {
    Foundry\Foundry::useCouponModel(\Foundry\Models\Coupon::class);
    Foundry\Foundry::usePlanModel(\Foundry\Models\Subscription\Plan::class);
    Foundry\Foundry::useSubscriptionModel(\Foundry\Models\Subscription::class);
});
