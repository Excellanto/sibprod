<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
	public function index(Request $request)
	{
		$this->authorize('viewAny', Permission::class);
		$permissions = Permission::query()->orderBy('name')->paginate(30);
		$grouped = Permission::all()->groupBy(function ($perm) {
			$parts = explode('.', $perm->name);
			return $parts[0] ?? 'general';
		});
		return Inertia::render('Admin/Permissions/Index', [
			'permissions' => $permissions,
			'grouped' => $grouped
		]);
	}

	public function store(Request $request)
	{
		$this->authorize('create', Permission::class);
		$data = $request->validate([
			'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
		]);
		Permission::create(['name' => $data['name'], 'guard_name' => 'web']);
		return back()->with('success', 'Permission created.');
	}

	public function update(Request $request, Permission $permission)
	{
		$this->authorize('update', $permission);
		$data = $request->validate([
			'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permission->id)],
		]);
		$permission->name = $data['name'];
		$permission->save();
		return back()->with('success', 'Permission updated.');
	}

	public function destroy(Permission $permission)
	{
		$this->authorize('delete', $permission);
		$permission->delete();
		return back()->with('success', 'Permission deleted.');
	}
}















