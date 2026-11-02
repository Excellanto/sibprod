<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogService;

class UserObserver
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->activityLogService->logCreated('users', $user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged() && count($user->getChanges()) > 0) {
            $this->activityLogService->logUpdated('users', $user);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->activityLogService->logDeleted('users', $user);
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        $this->activityLogService->log('users', 'restored', $user, [], "User '{$user->name}' was restored");
    }
}

