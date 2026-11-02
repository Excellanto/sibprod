<?php

namespace App\Policies;

use App\Models\Special;
use App\Models\User;

class SpecialPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('special.view');
	}

	public function view(User $user, Special $special): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('special.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('special.create');
	}

	public function update(User $user, Special $special): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('special.update');
	}

	public function delete(User $user, Special $special): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('special.delete');
	}
}











