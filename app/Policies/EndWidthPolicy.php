<?php

namespace App\Policies;

use App\Models\EndWidth;
use App\Models\User;

class EndWidthPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('end-width.view');
	}

	public function view(User $user, EndWidth $endWidth): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('end-width.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('end-width.create');
	}

	public function update(User $user, EndWidth $endWidth): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('end-width.update');
	}

	public function delete(User $user, EndWidth $endWidth): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('end-width.delete');
	}
}











