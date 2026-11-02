<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

if (!function_exists('settings')) {
    require_once app_path('helpers.php');
}

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        ...$request->user()->only('id', 'name', 'email'),
                        'role' => $request->user()->getRoleNames()->first(),
                        'is_admin' => $request->user()->hasAnyRole(['Admin', 'admin', 'Administrator']),
                        'permissions' => $request->user()->getAllPermissions()->pluck('name')->toArray(),
                    ]
                    : null,
            ],
            'appConfig' => fn () => [
                'title' => settings('site.name', 'Laravel'),
                'logo' => settings('site.logo'),
                'favicon' => settings('site.favicon'),
            ],
        ];
    }
}
