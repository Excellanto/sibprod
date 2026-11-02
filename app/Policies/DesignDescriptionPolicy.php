<?php

namespace App\Policies;

use App\Models\DesignDescription;
use App\Models\User;

class DesignDescriptionPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-description.view');
	}

	public function view(User $user, DesignDescription $designDescription): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-description.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-description.create');
	}

	public function update(User $user, DesignDescription $designDescription): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-description.update');
	}

	public function delete(User $user, DesignDescription $designDescription): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('design-description.delete');
	}
}
