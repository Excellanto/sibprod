<?php

namespace App\Policies;

use App\Models\LoopBand;
use App\Models\User;

class LoopBandPolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('loop-band.view');
	}

	public function view(User $user, LoopBand $loopBand): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('loop-band.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('loop-band.create');
	}

	public function update(User $user, LoopBand $loopBand): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('loop-band.update');
	}

	public function delete(User $user, LoopBand $loopBand): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('loop-band.delete');
	}
}











