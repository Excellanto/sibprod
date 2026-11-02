<?php

namespace App\Policies;

use App\Models\Buyer;
use App\Models\User;

class BuyerPolicy
{
	public function viewAny(User $user): bool
	{
		// Admin role has access via Gate::before, but check here too for safety
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buyers.view');
	}

	public function view(User $user, Buyer $buyer): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buyers.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buyers.create');
	}

	public function update(User $user, Buyer $buyer): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buyers.update');
	}

	public function delete(User $user, Buyer $buyer): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('buyers.delete');
	}
}
