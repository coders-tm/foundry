<?php

namespace Foundry\Policies\Subscription;

use Foundry\Models\Subscription\Plan;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class PlanPolicy
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
        return $admin->canAny(['plans:read', 'plans:write', 'plans:editor']);
    }

    /**
     * Determine whether the admin can view the model.
     */
    public function view(Model $admin, Plan $plan): bool
    {
        return $admin->canAny(['plans:read', 'plans:write', 'plans:editor']);
    }

    /**
     * Determine whether the admin can create models.
     */
    public function create(Model $admin): bool
    {
        return $admin->canAny(['plans:write', 'plans:editor']);
    }

    /**
     * Determine whether the admin can update the model.
     */
    public function update(Model $admin, Plan $plan): bool
    {
        return $admin->canAny(['plans:write', 'plans:editor']) && ($plan->user_id == $admin->id || $plan->hasUser($admin->id));
    }

    /**
     * Determine whether the admin can delete the model.
     */
    public function delete(Model $admin): bool
    {
        return $admin->can('plans:write');
    }

    /**
     * Determine whether the admin can restore the model.
     */
    public function restore(Model $admin): bool
    {
        return $admin->can('plans:write');
    }

    /**
     * Determine whether the admin can permanently delete the model.
     */
    public function forceDelete(Model $admin): bool
    {
        return $admin->can('plans:write');
    }
}
