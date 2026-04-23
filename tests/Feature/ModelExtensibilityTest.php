<?php

namespace Foundry\Tests\Feature;

use Foundry\Foundry;
use Foundry\Models\Order\DiscountLine;
use Foundry\Models\Redeem;
use Foundry\Tests\BaseTestCase;
use Workbench\App\Models\Coupon;
use Workbench\App\Models\Plan;
use Workbench\App\Models\Subscription;

class ModelExtensibilityTest extends BaseTestCase
{
    public function test_coupon_model_can_be_configured()
    {
        // Configure to use custom Coupon model
        Foundry::useCouponModel(Coupon::class);

        $this->assertEquals(Coupon::class, Foundry::$couponModel);
    }

    public function test_subscription_coupon_relationship_uses_configured_model()
    {
        // Configure to use custom Coupon model
        Foundry::useCouponModel(Coupon::class);

        $subscription = new \Foundry\Models\Subscription;

        // Verify the coupon relationship uses the configured model
        $this->assertEquals(Coupon::class, $subscription->coupon()->getRelated()::class);
    }

    public function test_redeem_coupon_relationship_uses_configured_model()
    {
        // Configure to use custom Coupon model
        Foundry::useCouponModel(Coupon::class);

        $redeem = new Redeem;

        // Verify the coupon relationship uses the configured model
        $this->assertEquals(Coupon::class, $redeem->coupon()->getRelated()::class);
    }

    public function test_discount_line_coupon_relationship_uses_configured_model()
    {
        // Configure to use custom Coupon model
        Foundry::useCouponModel(Coupon::class);

        $discountLine = new DiscountLine;

        // Verify the coupon relationship uses the configured model
        $this->assertEquals(Coupon::class, $discountLine->coupon()->getRelated()::class);
    }

    public function test_extended_subscription_can_override_can_apply_coupon_without_type_error()
    {
        // This test verifies that an extended subscription model can override
        // the canApplyCoupon method without PHP type compatibility errors

        // Configure to use custom Coupon model
        Foundry::useCouponModel(Coupon::class);

        // Create a mock extended subscription class that overrides canApplyCoupon
        // This mimics what users do in their projects when extending the Subscription model
        $mockSubscription = new class extends \Foundry\Models\Subscription
        {
            // This override should not cause a type compatibility error
            // because we removed the type hints from the base method
            public function canApplyCoupon($coupon = null)
            {
                // Custom implementation could use app-specific Coupon model
                return parent::canApplyCoupon($coupon);
            }
        };

        // If we get here without a fatal error, the test passes
        // The original issue would cause: "Declaration of ... must be compatible with ..."
        $this->assertInstanceOf(\Foundry\Models\Subscription::class, $mockSubscription);
    }

    public function test_workbench_extended_subscription_model_can_be_instantiated()
    {
        // This tests the actual extended Subscription model in workbench/app/Models/Subscription.php
        // which demonstrates a real-world example of extending the model

        Foundry::useCouponModel(Coupon::class);
        Foundry::useSubscriptionModel(Subscription::class);

        // Create an instance of the extended Subscription model
        $subscription = new Subscription;

        // Verify it's an instance of both the extended and base classes
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertInstanceOf(\Foundry\Models\Subscription::class, $subscription);

        // Verify it has the custom methods
        $this->assertTrue(method_exists($subscription, 'canApplyCoupon'));
        $this->assertTrue(method_exists($subscription, 'hasSpecialCoupon'));
    }

    public function test_plan_model_can_be_configured()
    {
        Foundry::usePlanModel(Plan::class);

        $this->assertEquals(Plan::class, Foundry::$planModel);
    }
}
