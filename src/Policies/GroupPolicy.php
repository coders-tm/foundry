<?php

namespace Foundry\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class GroupPolicy
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
        return $admin->canAny(['groups:read', 'groups:write', 'groups:editor']);
    }

    /**
     * Determine whether the admin can view the model.
     */
    public function view(Model $admin): bool
    {
        return $admin->canAny(['groups:read', 'groups:write', 'groups:editor']);
    }

    /**
     * Determine whether the admin can create models.
     */
    public function create(Model $admin): bool
    {
        return $admin->canAny(['groups:write', 'groups:editor']);
    }

    /**
     * Determine whether the admin can update the model.
     */
    public function update(Model $admin): bool
    {
        return $admin->canAny(['groups:write', 'groups:editor']);
    }

    /**
     * Determine whether the admin can delete the model.
     */
    public function delete(Model $admin): bool
    {
        return $admin->can('groups:write');
    }

    /**
     * Determine whether the admin can restore the model.
     */
    public function restore(Model $admin): bool
    {
        return $admin->can('groups:write');
    }

    /**
     * Determine whether the admin can permanently delete the model.
     */
    public function forceDelete(Model $admin): bool
    {
        return $admin->can('groups:write');
    }
}
