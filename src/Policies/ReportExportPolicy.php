<?php

namespace Foundry\Policies;

use Foundry\Models\ReportExport;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ReportExportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->canAny(['reports:read', 'reports:write', 'reports:editor']);
    }

    /**
     * Determine whether the user can view the report export.
     */
    public function view(Authenticatable $user, ReportExport $reportExport): bool
    {
        // Admin can view any with permission, or only their own
        if ($user->canAny(['reports:read', 'reports:write', 'reports:editor'])) {
            return true;
        }

        return $user->id === $reportExport->admin_id;
    }

    /**
     * Determine whether the user can delete the report export.
     */
    public function delete(Authenticatable $user, ReportExport $reportExport): bool
    {
        // Admin can delete any with permission, or only their own
        if ($user->can('reports:write')) {
            return true;
        }

        return $user->id === $reportExport->admin_id;
    }
}
