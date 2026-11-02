<?php

namespace App\Policies;

use App\Models\Reinforce;
use App\Models\User;

class ReinforcePolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('reinforce.view');
	}

	public function view(User $user, Reinforce $reinforce): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('reinforce.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('reinforce.create');
	}

	public function update(User $user, Reinforce $reinforce): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('reinforce.update');
	}

	public function delete(User $user, Reinforce $reinforce): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('reinforce.delete');
	}
}











