<?php

namespace App\Policies;

use App\Models\StrapLengthShortPart;
use App\Models\User;

class StrapLengthShortPartPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-short-part.view');
	}

	public function view(User $user, StrapLengthShortPart $strapLengthShortPart): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-short-part.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-short-part.create');
	}

	public function update(User $user, StrapLengthShortPart $strapLengthShortPart): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-short-part.update');
	}

	public function delete(User $user, StrapLengthShortPart $strapLengthShortPart): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-short-part.delete');
	}
}





