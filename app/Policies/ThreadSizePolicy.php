<?php

namespace App\Policies;

use App\Models\ThreadSize;
use App\Models\User;

class ThreadSizePolicy
{
	public function viewAny(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('thread-size.view');
	}

	public function view(User $user, ThreadSize $threadSize): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('thread-size.view');
	}

	public function create(User $user): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('thread-size.create');
	}

	public function update(User $user, ThreadSize $threadSize): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('thread-size.update');
	}

	public function delete(User $user, ThreadSize $threadSize): bool
	{
		if ($user->hasRole('admin') || $user->hasRole('Administrator')) {
			return true;
		}
		return $user->can('thread-size.delete');
	}
}











