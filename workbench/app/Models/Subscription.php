<?php

namespace Workbench\App\Models;

use Foundry\Models\Subscription as BaseSubscription;

class Subscription extends BaseSubscription
{
    public function canApplyCoupon($coupon = null)
    {
        return parent::canApplyCoupon($coupon);
    }

    public function hasSpecialCoupon(): bool
    {
        $coupon = $this->coupon;

        if ($coupon && method_exists($coupon, 'isSpecial')) {
            return $coupon->isSpecial();
        }

        return false;
    }
}
