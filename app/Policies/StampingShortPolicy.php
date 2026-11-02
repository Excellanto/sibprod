<?php

namespace App\Policies;

use App\Models\StampingShort;
use App\Models\User;

class StampingShortPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-short.view');
	}

	public function view(User $user, StampingShort $stampingShort): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-short.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-short.create');
	}

	public function update(User $user, StampingShort $stampingShort): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-short.update');
	}

	public function delete(User $user, StampingShort $stampingShort): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('stamping-short.delete');
	}
}











