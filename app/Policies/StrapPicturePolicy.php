<?php

namespace App\Policies;

use App\Models\StrapPicture;
use App\Models\User;

class StrapPicturePolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-pictures.view');
	}

	public function view(User $user, StrapPicture $strapPicture): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-pictures.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-pictures.create');
	}

	public function update(User $user, StrapPicture $strapPicture): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-pictures.update');
	}

	public function delete(User $user, StrapPicture $strapPicture): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-pictures.delete');
	}
}











