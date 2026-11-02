<?php

namespace App\Observers;

use App\Services\ActivityLogService;
use Spatie\Permission\Models\Permission;

class PermissionObserver
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Handle the Permission "created" event.
     */
    public function created(Permission $permission): void
    {
        $this->activityLogService->logCreated('permissions', $permission);
    }

    /**
     * Handle the Permission "updated" event.
     */
    public function updated(Permission $permission): void
    {
        if ($permission->wasChanged() && count($permission->getChanges()) > 0) {
            $this->activityLogService->logUpdated('permissions', $permission);
        }
    }

    /**
     * Handle the Permission "deleted" event.
     */
    public function deleted(Permission $permission): void
    {
        $this->activityLogService->logDeleted('permissions', $permission);
    }
}

