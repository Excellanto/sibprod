<?php

namespace App\Observers;

use App\Models\SiteSetting;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Cache;

class SiteSettingObserver
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Handle the SiteSetting "created" event.
     */
    public function created(SiteSetting $setting): void
    {
        $this->activityLogService->logCreated('settings', $setting);
        Cache::forget('app_settings');
    }

    /**
     * Handle the SiteSetting "updated" event.
     */
    public function updated(SiteSetting $setting): void
    {
        if ($setting->wasChanged() && count($setting->getChanges()) > 0) {
            $this->activityLogService->logUpdated('settings', $setting);
            Cache::forget('app_settings');
        }
    }

    /**
     * Handle the SiteSetting "deleted" event.
     */
    public function deleted(SiteSetting $setting): void
    {
        $this->activityLogService->logDeleted('settings', $setting);
        Cache::forget('app_settings');
    }
}

