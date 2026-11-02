<?php

namespace App\Policies;

use App\Models\NoteInfoLine1;
use App\Models\User;

class NoteInfoLine1Policy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line1.view');
	}

	public function view(User $user, NoteInfoLine1 $noteInfoLine1): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line1.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line1.create');
	}

	public function update(User $user, NoteInfoLine1 $noteInfoLine1): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line1.update');
	}

	public function delete(User $user, NoteInfoLine1 $noteInfoLine1): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line1.delete');
	}
}





