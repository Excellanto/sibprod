<?php

namespace App\Policies;

use App\Models\ProdDesign;
use App\Models\User;

class ProdDesignPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('prod-design.view');
	}

	public function view(User $user, ProdDesign $prodDesign): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('prod-design.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('prod-design.create');
	}

	public function update(User $user, ProdDesign $prodDesign): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('prod-design.update');
	}

	public function delete(User $user, ProdDesign $prodDesign): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('prod-design.delete');
	}
}











