<?php

namespace Foundry\Traits;

use Foundry\Models\Permission;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

trait HasPermission
{
    /**
     * Get all of the permissions for the model
     *
     * @return MorphToMany
     */
    public function permissions()
    {
        return $this->morphToMany(Permission::class, 'permissionable', 'permissionables', 'permissionable_id', 'permission_scope', 'id', 'scope')
            ->withPivot('access');
    }

    public function syncPermissions(Collection $permissions, bool $detach = true)
    {
        $permissions = $permissions->filter(function ($permission) {
            return isset($permission['scope']) && ! is_null($permission['access']);
        })->mapWithKeys(function ($permission) {
            return [$permission['scope'] => [
                'access' => $permission['access'],
            ]];
        });
        if ($detach) {
            $this->permissions()->sync($permissions);
        } else {
            $this->permissions()->syncWithoutDetaching($permissions);
        }
        // Clear cached permissions for this user
        Cache::forget("user_permissions_{$this->id}");

        return $this;
    }

    public function syncPermissionsDetaching(Collection $permissions)
    {
        return $this->syncPermissions($permissions, false);
    }

    /**
     * Return all the permissions the model has, both directly.
     */
    public function getAllPermissions(): Collection
    {
        return Cache::remember("user_permissions_{$this->id}", 300, function () {
            return $this->permissions->sort()->values();
        });
    }

    public function hasPermission($permission): bool
    {
        return (bool) $this->getAllPermissions()
            ->where('pivot.access', 1)
            ->where('scope', $permission)
            ->count();
    }

    public function hasAnyPermission(...$permissions): bool
    {
        return (bool) $this->getAllPermissions()
            ->where('pivot.access', 1)
            ->whereIn('scope', $permissions)
            ->count();
    }
}
