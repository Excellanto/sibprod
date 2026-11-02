<?php

namespace App\Http\Controllers;

use App\Support\AdminRoles;
use App\Support\DashboardViews;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            abort_unless(
                $user && $user->hasAnyRole(AdminRoles::names()),
                403,
                'Only admins can view and change roles.'
            );

            return $next($request);
        });
    }

    public function create()
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => null,
            ...$this->rolePermissionProps(Permission::query()->orderBy('name')->get()),
        ]);
    }

    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            ...$this->rolePermissionProps(Permission::query()->orderBy('name')->get()),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Permission>  $permissions
     * @return array<string, mixed>
     */
    private function rolePermissionProps($permissions): array
    {
        // Filter and sort permissions helper
        $actionOrder = ['view', 'create', 'update', 'delete', 'export', 'sync'];
        $sortPermissions = function ($perms) use ($actionOrder) {
            return $perms->sortBy(function ($perm) use ($actionOrder) {
                $parts = explode('.', $perm->name);
                $action = $parts[1] ?? '';
                $index = array_search($action, $actionOrder);

                return $index !== false ? $index : 999;
            })->values()->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ];
            })->toArray();
        };

        // User Management section
        $userManagementCategories = [
            'users' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'users.'))),
            'roles' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'roles.'))),
            'permissions' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'permissions.'))),
            'activity-logs' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'activity-logs.'))),
        ];
        $userManagementCategorySidebarPermissions = [
            'users' => 'users.show-in-sidebar',
            'roles' => 'roles.show-in-sidebar',
            'activity-logs' => 'activity-logs.show-in-sidebar',
        ];

        // Client Buyer Management section
        $clientBuyerCategories = [
            'clients' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'clients.'))),
            'buyers' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'buyers.'))),
        ];

        // Setting Management section
        $settingCategories = [
            'settings' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'settings.'))),
            'app-configuration' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'app-configuration.'))),
            'app-data-values' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'app-data-values.'))),
            'company-details' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'company-details.'))),
            'languages' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'languages.'))),
        ];
        $settingCategorySidebarPermissions = [
            'app-configuration' => 'app-configuration.show-in-sidebar',
            'app-data-values' => 'app-data-values.show-in-sidebar',
            'company-details' => 'company-details.show-in-sidebar',
            'languages' => 'languages.show-in-sidebar',
        ];

        // Production Data Management section
        $productionDataCategories = [
            'colour-object' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'colour-object.'))),
            'colours' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'colours.'))),
            'leather' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'leather.'))),
            'lining' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'lining.'))),
            'loop-band' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'loop-band.'))),
            'buckle' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'buckle.'))),
            'buckle-width' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'buckle-width.'))),
            'thread-size' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'thread-size.'))),
            'end-width' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'end-width.'))),
            'design-name' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'design-name.'))),
            'design-description' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'design-description.'))),
            'reinforce' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'reinforce.'))),
            'strap-length-long-part' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'strap-length-long-part.'))),
            'strap-length-short-part' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'strap-length-short-part.'))),
            'stamping-long' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'stamping-long.'))),
            'stamping-short' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'stamping-short.'))),
            'strap-pictures' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'strap-pictures.'))),
            'special' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'special.'))),
            'prod-design' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'prod-design.'))),
            'note-info-line1' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'note-info-line1.'))),
            'note-info-line2' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'note-info-line2.'))),
            'tools' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'tools.'))),
        ];

        // Order Management section
        $orderManagementCategories = [
            'process-order' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'process-order.'))),
            'model-template' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'model-template.'))),
            'production-orders' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'production-orders.'))),
            'quality-control' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'quality-control.'))),
            'packaging' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'packaging.'))),
        ];
        $orderManagementCategorySidebarPermissions = [
            'process-order' => 'process-order.show-in-sidebar',
            'model-template' => 'model-template.show-in-sidebar',
            'production-orders' => 'production-orders.show-in-sidebar',
            'quality-control' => 'quality-control.show-in-sidebar',
            'packaging' => 'packaging.show-in-sidebar',
        ];

        // Invoice Management section
        $invoiceManagementCategories = [
            'invoice-management' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'invoice-management.'))),
        ];
        $invoiceManagementCategorySidebarPermissions = [
            'invoice-management' => 'invoice-management.show-in-sidebar',
        ];

        // Reports section
        $reportsCategories = [
            'reports' => $sortPermissions($permissions->filter(fn ($p) => str_starts_with($p->name, 'reports.'))),
        ];
        $reportsCategorySidebarPermissions = [
            'reports' => 'reports.show-in-sidebar',
        ];

        // Filter out grouped permissions from the general permission matrix
        // These modules are already shown in expandable sections
        $groupedModules = [
            'users', 'roles', 'permissions', 'activity-logs',
            'clients', 'buyers',
            'settings', 'app-configuration', 'app-data-values', 'company-details', 'languages',
            'user-management', 'data-management', 'client-buyer-management', 'production-data-management', 'order-management',
            'colour-object', 'colours', 'leather', 'lining', 'loop-band',
            'buckle', 'buckle-width', 'thread-size', 'end-width',
            'design-name', 'design-description', 'reinforce', 'strap-length-long-part', 'strap-length-short-part',
            'stamping-long', 'stamping-short', 'strap-pictures',
            'special', 'prod-design', 'note-info-line1', 'note-info-line2', 'tools',
            'process-order', 'model-template', 'production-orders', 'quality-control', 'packaging',
            'invoice-management',
            'reports',
        ];
        $otherPermissions = $permissions->reject(function ($perm) use ($groupedModules) {
            $parts = explode('.', $perm->name);
            $module = $parts[0] ?? '';

            // Exclude if module is in grouped modules list
            return in_array($module, $groupedModules);
        });

        // Only create permission matrix if there are other permissions
        $permissionMatrix = $otherPermissions->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return $parts[0] ?? 'general';
        })->filter(function ($group) {
            // Remove empty groups
            return $group->isNotEmpty();
        });

        return [
            'permissionMatrix' => $permissionMatrix,
            'userManagementCategories' => $userManagementCategories,
            'userManagementCategorySidebarPermissions' => $userManagementCategorySidebarPermissions,
            'clientBuyerCategories' => $clientBuyerCategories,
            'settingCategories' => $settingCategories,
            'settingCategorySidebarPermissions' => $settingCategorySidebarPermissions,
            'productionDataCategories' => $productionDataCategories,
            'orderManagementCategories' => $orderManagementCategories,
            'orderManagementCategorySidebarPermissions' => $orderManagementCategorySidebarPermissions,
            'invoiceManagementCategories' => $invoiceManagementCategories,
            'invoiceManagementCategorySidebarPermissions' => $invoiceManagementCategorySidebarPermissions,
            'reportsCategories' => $reportsCategories,
            'reportsCategorySidebarPermissions' => $reportsCategorySidebarPermissions,
        ];
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->select(['id', 'name', 'dashboard_view'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'dashboardViews' => DashboardViews::options(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Role::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name', $this->reservedAdminRoleName()],
            'permissions' => ['array'],
        ]);
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'dashboard_view' => DashboardViews::defaultForRoleName($data['name']),
        ]);
        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id), $this->reservedAdminRoleName($role)],
            'permissions' => ['array'],
        ]);
        if (AdminRoles::is($role->name) && $data['name'] !== $role->name) {
            throw ValidationException::withMessages([
                'name' => 'The Admin role name cannot be changed.',
            ]);
        }
        $role->name = $data['name'];
        $role->save();
        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        if (AdminRoles::is($role->name)) {
            return back()->with('error', 'The Admin role cannot be deleted.');
        }

        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    public function updateDashboardView(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'dashboard_view' => ['required', 'string', Rule::in(DashboardViews::keys())],
        ]);

        $role->dashboard_view = $data['dashboard_view'];
        $role->save();

        return back()->with('success', 'Dashboard view updated.');
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'permissions' => ['array'],
        ]);
        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', 'Role permissions updated.');
    }

    private function reservedAdminRoleName(?Role $role = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($role): void {
            if (! is_string($value) || ! AdminRoles::is($value)) {
                return;
            }

            if ($role && AdminRoles::is($role->name)) {
                return;
            }

            $fail('The Admin role name is reserved.');
        };
    }
}
