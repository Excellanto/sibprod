<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardDataService;
use App\Support\DashboardViews;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __construct(private DashboardDataService $dashboard)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless(
            $user
            && (
                $user->hasAnyRole(['Admin', 'admin', 'Administrator'])
                || $user->can('admin-panel-access')
            ),
            403,
            'You do not have access to the admin panel.'
        );

        $isAdmin = $user->hasAnyRole(['Admin', 'admin', 'Administrator']);
        $assigned = $this->resolveDashboardView($user);
        $requested = $request->query('view');
        $view = $isAdmin && DashboardViews::isValid(is_string($requested) ? $requested : null)
            ? $requested
            : $assigned;
        $showExecutive = $view === DashboardViews::EXECUTIVE;

        $stats = $showExecutive && $isAdmin ? [
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
        ] : null;

        $lastLogins = $showExecutive && $isAdmin
            ? User::query()
                ->orderByDesc('last_login_at')
                ->limit(10)
                ->get(['id', 'name', 'email', 'last_login_at'])
            : [];

        return Inertia::render('Admin/Dashboard', [
            'dashboardView' => $view,
            'viewLabel' => DashboardViews::label($view),
            'canSwitchView' => $isAdmin,
            'dashboardViews' => $isAdmin ? DashboardViews::options() : [],
            'showUserStats' => $showExecutive && $isAdmin,
            'stats' => $stats,
            'lastLogins' => $lastLogins,
            'orderFunnel' => $showExecutive ? $this->dashboard->orderFunnel() : [],
            'productionPipeline' => $showExecutive ? $this->dashboard->productionPipeline() : ['total' => 0, 'segments' => []],
            'topClients' => $showExecutive ? $this->dashboard->topClients() : [],
            'production' => in_array($view, [DashboardViews::PRODUCTION, DashboardViews::EXECUTIVE], true)
                ? $this->dashboard->production()
                : null,
            'sales' => in_array($view, [DashboardViews::SALES, DashboardViews::EXECUTIVE], true)
                ? $this->dashboard->sales()
                : null,
            'packing' => in_array($view, [DashboardViews::PACKING, DashboardViews::EXECUTIVE], true)
                ? $this->dashboard->packing()
                : null,
        ]);
    }

    private function resolveDashboardView(User $user): string
    {
        $views = $user->roles()
            ->pluck('dashboard_view')
            ->filter(fn ($view) => DashboardViews::isValid($view))
            ->unique()
            ->values();

        if ($views->contains(DashboardViews::EXECUTIVE)) {
            return DashboardViews::EXECUTIVE;
        }

        if ($views->count() === 1) {
            return (string) $views->first();
        }

        if ($views->count() > 1) {
            return DashboardViews::EXECUTIVE;
        }

        if ($user->hasAnyRole(['Admin', 'admin', 'Administrator'])) {
            return DashboardViews::EXECUTIVE;
        }

        return DashboardViews::PRODUCTION;
    }
}
