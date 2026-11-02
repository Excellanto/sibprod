<?php

namespace App\Policies;

use App\Models\Buckle;
use App\Models\User;

class BucklePolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle.view');
	}

	public function view(User $user, Buckle $buckle): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle.create');
	}

	public function update(User $user, Buckle $buckle): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle.update');
	}

	public function delete(User $user, Buckle $buckle): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle.delete');
	}
}











