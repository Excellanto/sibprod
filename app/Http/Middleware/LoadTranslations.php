<?php

namespace App\Http\Middleware;

use App\Models\LanguageString;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LoadTranslations
{
	public function handle(Request $request, Closure $next): Response
	{
		$locale = app()->getLocale();
		$translations = LanguageString::query()
			->where('lang', $locale)
			->get(['file', 'key', 'value'])
			->groupBy('file')
			->map(function ($group) {
				$entries = [];
				foreach ($group as $item) {
					$entries[$item->key] = $item->value ?? '';
				}
				return $entries;
			});

		Inertia::share('translations', $translations);
		return $next($request);
	}
}















