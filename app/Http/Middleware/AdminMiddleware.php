<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
	public function handle(Request $request, Closure $next): Response
	{
		$user = $request->user();
		if (
			!$user
			|| (
				!$user->hasRole('admin')
				&& !$user->hasRole('Admin')
				&& !$user->hasRole('Administrator')
				&& !$user->can('admin-panel-access')
			)
		) {
			abort(403, 'You do not have access to the admin panel.');
		}
		return $next($request);
	}
}


