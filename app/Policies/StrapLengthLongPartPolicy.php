<?php

namespace App\Policies;

use App\Models\StrapLengthLongPart;
use App\Models\User;

class StrapLengthLongPartPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-long-part.view');
	}

	public function view(User $user, StrapLengthLongPart $strapLengthLongPart): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-long-part.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-long-part.create');
	}

	public function update(User $user, StrapLengthLongPart $strapLengthLongPart): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-long-part.update');
	}

	public function delete(User $user, StrapLengthLongPart $strapLengthLongPart): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('strap-length-long-part.delete');
	}
}





