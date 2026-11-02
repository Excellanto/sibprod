<?php

namespace App\Policies;

use App\Models\NoteInfoLine2;
use App\Models\User;

class NoteInfoLine2Policy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line2.view');
	}

	public function view(User $user, NoteInfoLine2 $noteInfoLine2): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line2.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line2.create');
	}

	public function update(User $user, NoteInfoLine2 $noteInfoLine2): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line2.update');
	}

	public function delete(User $user, NoteInfoLine2 $noteInfoLine2): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('note-info-line2.delete');
	}
}





