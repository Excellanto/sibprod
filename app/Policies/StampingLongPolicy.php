<?php

namespace App\Policies;

use App\Models\StampingLong;
use App\Models\User;

class StampingLongPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-long.view');
	}

	public function view(User $user, StampingLong $stampingLong): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-long.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-long.create');
	}

	public function update(User $user, StampingLong $stampingLong): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-long.update');
	}

	public function delete(User $user, StampingLong $stampingLong): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-long.delete');
	}
}











