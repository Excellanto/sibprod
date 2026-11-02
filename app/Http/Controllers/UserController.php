<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\AdminRoles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
	public function create()
	{
		$this->authorize('create', User::class);
		$isAdmin = $this->isAdmin();

		return Inertia::render('Admin/Users/Create', [
			'roles' => $isAdmin ? $this->assignableRoles() : [],
			'canManageRoles' => $isAdmin,
			'permissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
		]);
	}

	public function edit(User $user)
	{
		$this->authorizeProfileEdit($user);
		$user->load(['roles', 'permissions']);
		$isAdmin = $this->isAdmin();

		return Inertia::render('Admin/Users/Edit', [
			'user' => [
				'id' => $user->id,
				'name' => $user->name,
				'email' => $user->email,
				'roles' => $isAdmin ? $user->roles->pluck('name') : [],
				'permissions' => $user->permissions->pluck('name'),
			],
			'roles' => $isAdmin ? $this->assignableRoles($user) : [],
			'canManageRoles' => $isAdmin,
			'permissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
		]);
	}

	public function index(Request $request)
	{
		$this->authorize('viewAny', User::class);
		$search = $request->string('search')->toString();
		$users = User::query()
			->select(['id', 'name', 'email'])
			->when($search, function ($q) use ($search) {
				$q->where(function ($qq) use ($search) {
					$qq->where('name', 'like', "%{$search}%")
						->orWhere('email', 'like', "%{$search}%");
				});
			})
			->with(['roles:id,name'])
			->orderByDesc('id')
			->paginate(10)
			->withQueryString();

		return Inertia::render('Admin/Users/Index', [
			'users' => UserResource::collection($users),
		]);
	}

	public function store(Request $request)
	{
		$this->authorize('create', User::class);
		$data = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
			'password' => ['required', 'string', 'min:8', 'confirmed'],
			'roles' => ['array'],
			'permissions' => ['array'],
		]);

		if ($this->isAdmin() && !empty($data['roles'])) {
			$this->assertAdminRoleCanBeAssigned(new User, $data['roles']);
		}

		$user = User::create([
			'name' => $data['name'],
			'email' => $data['email'],
			'password' => $data['password'],
		]);

		if ($this->isAdmin() && !empty($data['roles'])) {
			$user->syncRoles($data['roles']);
		}
		if (!empty($data['permissions'])) {
			$user->syncPermissions($data['permissions']);
		}

		return back()->with('success', 'User created.');
	}

	public function update(Request $request, User $user)
	{
		$this->authorizeProfileEdit($user);
		$data = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
			'password' => ['nullable', 'string', 'min:8', 'confirmed'],
			'roles' => ['array'],
			'permissions' => ['array'],
		]);

		$user->name = $data['name'];
		$user->email = $data['email'];
		if (!empty($data['password'])) {
			$user->password = $data['password'];
		}
		$user->save();

		if ($this->isAdmin() && isset($data['roles'])) {
			$this->assertAdminRoleCanBeAssigned($user, $data['roles']);
			$user->syncRoles($data['roles']);
		}
		if (isset($data['permissions'])) {
			$user->syncPermissions($data['permissions']);
		}

		return back()->with('success', 'User updated.');
	}

	public function destroy(User $user)
	{
		$this->authorize('delete', $user);
		$user->delete();
		return back()->with('success', 'User deleted.');
	}

	public function assignRoles(Request $request, User $user)
	{
		abort_unless($this->isAdmin(), 403, 'Only admins can view and change roles.');
		$this->authorize('update', $user);
		$data = $request->validate([
			'roles' => ['array'],
		]);
		$roles = $data['roles'] ?? [];
		$this->assertAdminRoleCanBeAssigned($user, $roles);
		$user->syncRoles($roles);
		return back()->with('success', 'Roles updated.');
	}

	public function givePermissions(Request $request, User $user)
	{
		$this->authorize('update', $user);
		$data = $request->validate([
			'permissions' => ['array'],
		]);
		$user->syncPermissions($data['permissions'] ?? []);
		return back()->with('success', 'Permissions updated.');
	}

	private function isAdmin(): bool
	{
		$user = auth()->user();

		return $user && $user->hasAnyRole(AdminRoles::names());
	}

	/**
	 * Admin is listed only for a user who already has that role.
	 */
	private function assignableRoles(?User $user = null)
	{
		$held = collect($user?->roles->pluck('name') ?? [])
			->map(fn ($name) => strtolower((string) $name));

		return Role::query()
			->orderBy('name')
			->get(['id', 'name'])
			->reject(function ($role) use ($held) {
				return AdminRoles::is($role->name) && ! $held->contains(strtolower($role->name));
			})
			->values();
	}

	/**
	 * @param  array<int, mixed>  $roles
	 */
	private function assertAdminRoleCanBeAssigned(User $user, array $roles): void
	{
		$held = $user->roles
			->pluck('name')
			->map(fn ($name) => strtolower((string) $name));

		$requestsNewAdminRole = collect($roles)->contains(function ($role) use ($held) {
			$name = is_numeric($role)
				? Role::query()->whereKey($role)->value('name')
				: (is_string($role) ? $role : null);

			return AdminRoles::is($name) && ! $held->contains(strtolower((string) $name));
		});

		if ($requestsNewAdminRole) {
			throw ValidationException::withMessages([
				'roles' => 'The Admin role cannot be assigned to another role.',
			]);
		}
	}

	private function authorizeProfileEdit(User $user): void
	{
		$actor = auth()->user();
		if ($actor && $actor->id === $user->id) {
			return;
		}

		$this->authorize('update', $user);
	}
}


