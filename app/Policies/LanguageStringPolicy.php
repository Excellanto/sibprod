<?php

namespace App\Policies;

use App\Models\LanguageString;
use App\Models\User;

class LanguageStringPolicy
{
	public function viewAny(User $user): bool
	{
		return $user->can('languages.view');
	}

	public function update(User $user, LanguageString $model): bool
	{
		return $user->can('languages.update');
	}

	public function sync(User $user): bool
	{
		return $user->can('languages.sync');
	}
}















