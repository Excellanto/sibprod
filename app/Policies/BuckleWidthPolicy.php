<?php

namespace App\Policies;

use App\Models\BuckleWidth;
use App\Models\User;

class BuckleWidthPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle-width.view');
	}

	public function view(User $user, BuckleWidth $buckleWidth): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle-width.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle-width.create');
	}

	public function update(User $user, BuckleWidth $buckleWidth): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle-width.update');
	}

	public function delete(User $user, BuckleWidth $buckleWidth): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buckle-width.delete');
	}
}











