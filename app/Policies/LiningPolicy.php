<?php

namespace App\Policies;

use App\Models\Lining;
use App\Models\User;

class LiningPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('lining.view');
	}

	public function view(User $user, Lining $lining): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('lining.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('lining.create');
	}

	public function update(User $user, Lining $lining): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('lining.update');
	}

	public function delete(User $user, Lining $lining): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('lining.delete');
	}
}











