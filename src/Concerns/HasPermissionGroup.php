<?php

namespace Foundry\Concerns;

use Foundry\Models\Module;
use Foundry\Models\Permission;
use Illuminate\Support\Collection;

trait HasPermissionGroup
{
    use HasGroup, HasPermission;

    /**
     * Return all the permissions the model has via groups.
     */
    public function getPermissionsViaGroups(): Collection
    {
        return $this->loadMissing('groups', 'groups.permissions')
            ->groups->flatMap(function ($group) {
                return $group->permissions->filter(function ($permission) {
                    return ! is_null($permission->pivot->access);
                });
            })
            ->sort()
            ->values();
    }

    /**
     * Return all the permissions the model has, both directly and via groups.
     */
    public function getAllPermissions(): Collection
    {
        $permissions = $this->permissions;

        if ($this->groups->count()) {
            $permissions = $permissions->merge($this->getPermissionsViaGroups());
        }

        return $permissions->sort()->values();
    }

    public function getModulesAttribute()
    {
        if ($this->is_super_admin) {
            $modules = Module::all();
        } else {
            $permissions = $this->getAllPermissions()
                ->filter(function ($permission) {
                    return $permission->pivot->access == 1;
                });
            $permissionByModule = $permissions->groupBy('module_key');

            $modules = Module::orderBy('sort_order')->find($permissionByModule->keys());
        }

        return $modules->makeHidden(['created_at', 'deleted_at', 'updated_at'])->map(function ($item) {
            return array_merge($item->toArray(), [
                'label' => __($item['name']),
            ]);
        });
    }

    public function getScopes()
    {
        if ($this->is_super_admin) {
            return Permission::pluck('scope')->toArray();
        } else {
            $permissions = $this->getAllPermissions()
                ->filter(function ($permission) {
                    return $permission->pivot->access == 1;
                });

            return $permissions->pluck('scope')->toArray();
        }
    }
}
