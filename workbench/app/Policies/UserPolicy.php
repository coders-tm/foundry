<?php

namespace Workbench\App\Policies;

use Foundry\Models\Admin as Model;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     */
    public function before(Model $admin, $ability)
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
        return $admin->canAny(['users:read', 'users:write', 'users:editor']);
    }

    /**
     * Determine whether the admin can view the model.
     */
    public function view(Model $admin): bool
    {
        return $admin->canAny(['users:read', 'users:write', 'users:editor']);
    }

    /**
     * Determine whether the admin can create models.
     */
    public function create(Model $admin): bool
    {
        return $admin->canAny(['users:write', 'users:editor']);
    }

    /**
     * Determine whether the admin can update the model.
     */
    public function update(Model $admin): bool
    {
        return $admin->canAny(['users:write', 'users:editor']);
    }

    /**
     * Determine whether the admin can delete the model.
     */
    public function delete(Model $admin): bool
    {
        return $admin->can('users:write');
    }

    /**
     * Determine whether the admin can restore the model.
     */
    public function restore(Model $admin): bool
    {
        return $admin->can('users:write');
    }

    /**
     * Determine whether the admin can permanently delete the model.
     */
    public function forceDelete(Model $admin): bool
    {
        return $admin->can('users:write');
    }
}
