<?php

namespace App\Policies;

use App\Models\DesignName;
use App\Models\User;

class DesignNamePolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-name.view');
	}

	public function view(User $user, DesignName $designName): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-name.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-name.create');
	}

	public function update(User $user, DesignName $designName): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-name.update');
	}

	public function delete(User $user, DesignName $designName): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-name.delete');
	}
}
