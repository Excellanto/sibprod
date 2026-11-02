<?php

namespace App\Observers;

use App\Services\ActivityLogService;
use Spatie\Permission\Models\Role;

class RoleObserver
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Handle the Role "created" event.
     */
    public function created(Role $role): void
    {
        $this->activityLogService->logCreated('roles', $role);
    }

    /**
     * Handle the Role "updated" event.
     */
    public function updated(Role $role): void
    {
        if ($role->wasChanged() && count($role->getChanges()) > 0) {
            $this->activityLogService->logUpdated('roles', $role);
        }
    }

    /**
     * Handle the Role "deleted" event.
     */
    public function deleted(Role $role): void
    {
        $this->activityLogService->logDeleted('roles', $role);
    }
}

