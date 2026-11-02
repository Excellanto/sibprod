<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

class SettingPolicy
{
	public function viewAny(User $user): bool
	{
		// Always log to see if policy is being called
		\Log::info('SettingPolicy::viewAny CALLED', [
			'user_id' => $user->id,
			'hasRole(Admin)' => $user->hasRole('Admin'),
			'hasRole(admin)' => $user->hasRole('admin'),
			'hasRole(Administrator)' => $user->hasRole('Administrator'),
		]);
		
		// Admin role has access - check all variations
		if ($user->hasRole('Admin') || $user->hasRole('admin') || $user->hasRole('Administrator')) {
			\Log::info('SettingPolicy::viewAny returning TRUE for admin');
			return true;
		}
		
		\Log::info('SettingPolicy::viewAny checking permission', [
			'can(app-configuration.view)' => $user->can('app-configuration.view'),
		]);
		
		return $user->can('app-configuration.view');
	}

	public function update(User $user, SiteSetting $setting): bool
	{
		// Admin role has access via Gate::before, but check here too for safety
		if ($user->hasRole('Admin') || $user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('app-configuration.update');
	}
}





