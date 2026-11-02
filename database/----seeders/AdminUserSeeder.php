<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
	public function run(): void
	{
		$user = User::firstOrCreate(
			['email' => 'admin@example.com'],
			[
				'name' => 'Administrator',
				'password' => Hash::make('password'),
				'email_verified_at' => now()
			]
		);
		$user->assignRole('admin');
	}
}















