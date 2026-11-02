<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
	public function run(): void
	{
		$admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);

		$allPermissions = Permission::pluck('name')->toArray();
		$admin->syncPermissions($allPermissions);

		$managerPerms = [
			'admin-panel-access',
			'users.view', 'users.update',
			'roles.view',
			'permissions.view',
			'settings.view',
			'languages.view', 'languages.update',
		];
		$manager->syncPermissions($managerPerms);
	}
}















