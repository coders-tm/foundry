<?php

namespace Foundry\Policies;

use Foundry\Models\Coupon;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class ExchangeRatePolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     */
    public function before(Model $admin, string $ability)
    {
        if ($admin->is_super_admin) {
            return true;
        }
    }

    /**
     * Determine whether the admin can view any models.
     */
    public function viewAny(Model $admin): bool
    {
        return $admin->canAny(['settings:read', 'settings:write', 'settings:editor']);
    }

    /**
     * Determine whether the admin can view the model.
     */
    public function view(Model $admin, Coupon $coupon): bool
    {
        return $admin->canAny(['settings:read', 'settings:write', 'settings:editor']);
    }

    /**
     * Determine whether the admin can create models.
     */
    public function create(Model $admin): bool
    {
        return $admin->canAny(['settings:write', 'settings:editor']);
    }

    /**
     * Determine whether the admin can update the model.
     */
    public function update(Model $admin, Coupon $coupon): bool
    {
        return $admin->canAny(['settings:write', 'settings:editor']);
    }

    /**
     * Determine whether the admin can delete the model.
     */
    public function delete(Model $admin): bool
    {
        return $admin->can('settings:write');
    }

    /**
     * Determine whether the admin can restore the model.
     */
    public function restore(Model $admin): bool
    {
        return $admin->can('settings:write');
    }

    /**
     * Determine whether the admin can permanently delete the model.
     */
    public function forceDelete(Model $admin): bool
    {
        return $admin->can('settings:write');
    }
}
