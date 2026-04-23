<?php

namespace Foundry\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class FilePolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     */
    public function before(Model $admin, string $ability)
    {
        if (isset($admin->is_super_admin) && $admin->is_super_admin) {
            return true;
        }
    }

    /**
     * Determine whether the admin can view any models.
     */
    public function viewAny(Model $admin): bool
    {
        return $admin->canAny(['files:read', 'files:write']);
    }

    /**
     * Determine whether the admin can view the model.
     */
    public function view(Model $admin): bool
    {
        return $admin->canAny(['files:read', 'files:write']);
    }

    /**
     * Determine whether the admin can create models.
     */
    public function create(Model $admin): bool
    {
        return $admin->can('files:write');
    }

    /**
     * Determine whether the admin can update the model.
     */
    public function update(Model $admin): bool
    {
        return $admin->can('files:write');
    }

    /**
     * Determine whether the admin can delete the model.
     */
    public function delete(Model $admin): bool
    {
        return $admin->can('files:write');
    }
}
