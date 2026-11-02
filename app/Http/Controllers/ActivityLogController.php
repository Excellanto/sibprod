<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of activity logs.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ActivityLog::class);

        $query = ActivityLog::query()
            ->with('user:id,name,email')
            ->accessibleModules(Auth::user())
            ->latest();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('module')) {
            $query->forModule($request->module);
        }

        if ($request->filled('action')) {
            $query->forAction($request->action);
        }

        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Paginate results
        $logs = $query->paginate(15)->withQueryString();

        // Get filter options
        $modules = $this->getAccessibleModules(Auth::user());
        $actions = ActivityLog::query()
            ->accessibleModules(Auth::user())
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        $users = User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'module', 'action', 'user_id', 'start_date', 'end_date']),
            'modules' => $modules,
            'actions' => $actions,
            'users' => $users,
        ]);
    }

    /**
     * Display the specified activity log.
     */
    public function show(ActivityLog $activityLog)
    {
        $this->authorize('view', $activityLog);

        // Check if user has access to this module
        $user = Auth::user();
        if (!$user->hasRole('Super Admin')) {
            $accessibleModules = $this->getAccessibleModules($user);
            if (!in_array($activityLog->module, $accessibleModules)) {
                abort(403, 'You do not have permission to view logs for this module.');
            }
        }

        $activityLog->load('user:id,name,email', 'subject');

        return Inertia::render('Admin/ActivityLogs/Show', [
            'log' => $activityLog,
        ]);
    }

    /**
     * Export activity logs (optional feature).
     */
    public function export(Request $request)
    {
        $this->authorize('export', ActivityLog::class);

        $query = ActivityLog::query()
            ->with('user:id,name,email')
            ->accessibleModules(Auth::user())
            ->latest();

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%");
            });
        }

        if ($request->filled('module')) {
            $query->forModule($request->module);
        }

        if ($request->filled('action')) {
            $query->forAction($request->action);
        }

        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $logs = $query->limit(10000)->get();

        // Generate CSV
        $filename = 'activity-logs-' . now()->format('Y-m-d-His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, [
                'ID',
                'Date/Time',
                'User',
                'Module',
                'Action',
                'Description',
                'IP Address',
            ]);

            // Add data rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? $log->user_name ?? 'System',
                    $log->module,
                    $log->action,
                    $log->description,
                    $log->ip_address,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get accessible modules for a user.
     */
    protected function getAccessibleModules(User $user): array
    {
        // Super admin sees all modules
        if ($user->hasRole('Super Admin')) {
            return ActivityLog::query()
                ->distinct()
                ->pluck('module')
                ->sort()
                ->values()
                ->toArray();
        }

        // Get modules from user's permissions
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $modules = [];

        foreach ($permissions as $permission) {
            $parts = explode('.', $permission);
            if (count($parts) >= 2) {
                $modules[] = $parts[0];
            }
        }

        // Remove duplicates and sort
        $modules = array_unique($modules);
        sort($modules);

        return $modules;
    }

    /**
     * Get activity statistics (optional feature).
     */
    public function statistics(Request $request)
    {
        $this->authorize('viewAny', ActivityLog::class);

        $query = ActivityLog::query()->accessibleModules(Auth::user());

        // Apply date range filter
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $totalLogs = $query->count();
        
        $logsByModule = (clone $query)
            ->selectRaw('module, COUNT(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->get();

        $logsByAction = (clone $query)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get();

        $topUsers = (clone $query)
            ->selectRaw('user_id, COUNT(*) as count')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->with('user:id,name')
            ->get();

        return response()->json([
            'total_logs' => $totalLogs,
            'logs_by_module' => $logsByModule,
            'logs_by_action' => $logsByAction,
            'top_users' => $topUsers,
        ]);
    }
}
