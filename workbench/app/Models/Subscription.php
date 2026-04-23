<?php

namespace Workbench\App\Models;

use Foundry\Models\Subscription as BaseSubscription;

/**
 * Example of extending the Subscription model with a custom Coupon model.
 *
 * This demonstrates how projects can now extend the Subscription model
 * and override methods that work with Coupon without encountering
 * PHP type compatibility errors.
 */
class Subscription extends BaseSubscription
{
    /**
     * Override canApplyCoupon to use project-specific Coupon model.
     *
     * Before the fix, this would cause a fatal error:
     * "Declaration of App\Central\Models\Subscription::canApplyCoupon(?App\Central\Models\Coupon $coupon = null): ?App\Central\Models\Coupon
     *  must be compatible with Foundry\Models\Subscription::canApplyCoupon(?Foundry\Models\Coupon $coupon = null): ?Foundry\Models\Coupon"
     *
     * After the fix, by removing type hints from the base method, this works correctly.
     */
    public function canApplyCoupon($coupon = null)
    {
        // Projects can now add custom logic here
        // and work with their own extended Coupon model

        // Call parent implementation
        return parent::canApplyCoupon($coupon);
    }

    /**
     * Example of adding additional coupon-related methods
     * that use the project's Coupon model.
     */
    public function hasSpecialCoupon(): bool
    {
        $coupon = $this->coupon;

        // Custom logic using the extended Coupon model
        if ($coupon && method_exists($coupon, 'isSpecial')) {
            return $coupon->isSpecial();
        }

        return false;
    }
}
