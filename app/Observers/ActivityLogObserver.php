<?php

namespace App\Observers;

use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    protected ActivityLogService $activityLogService;
    protected string $module;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Set the module name for this observer.
     */
    public function setModule(string $module): void
    {
        $this->module = $module;
    }

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->activityLogService->logCreated($this->module, $model);
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        // Only log if there are actual changes
        if ($model->wasChanged()) {
            $this->activityLogService->logUpdated($this->module, $model);
        }
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->activityLogService->logDeleted($this->module, $model);
    }

    /**
     * Handle the Model "restored" event.
     */
    public function restored(Model $model): void
    {
        $this->activityLogService->log($this->module, 'restored', $model, [], "Restored {$this->module}");
    }
}
