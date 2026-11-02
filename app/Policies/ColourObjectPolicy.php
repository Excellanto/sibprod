<?php

namespace App\Policies;

use App\Models\ColourObject;
use App\Models\User;

class ColourObjectPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('colour-object.view');
	}

	public function view(User $user, ColourObject $colourObject): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('colour-object.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('colour-object.create');
	}

	public function update(User $user, ColourObject $colourObject): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('colour-object.update');
	}

	public function delete(User $user, ColourObject $colourObject): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('colour-object.delete');
	}
}











