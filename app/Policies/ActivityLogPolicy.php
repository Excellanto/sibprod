<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActivityLogPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any activity logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('activity-logs.view');
    }

    /**
     * Determine whether the user can view the activity log.
     */
    public function view(User $user, ActivityLog $activityLog): bool
    {
        // Must have view permission
        if (!$user->can('activity-logs.view')) {
            return false;
        }

        // Super admin can view everything
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Check if user has access to the module
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $accessibleModules = [];

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission);
            if (count($parts) >= 2) {
                $accessibleModules[] = $parts[0];
            }
        }

        return in_array($activityLog->module, $accessibleModules);
    }

    /**
     * Determine whether the user can export activity logs.
     */
    public function export(User $user): bool
    {
        return $user->can('activity-logs.export');
    }

    /**
     * Determine whether the user can delete activity logs.
     */
    public function delete(User $user, ActivityLog $activityLog): bool
    {
        // Only super admin can delete logs (optional - you might want to prevent deletion entirely)
        return $user->hasRole('Super Admin') && $user->can('activity-logs.delete');
    }

    /**
     * Determine whether the user can permanently delete activity logs.
     */
    public function forceDelete(User $user, ActivityLog $activityLog): bool
    {
        // Typically, activity logs should not be force deleted
        return false;
    }
}
