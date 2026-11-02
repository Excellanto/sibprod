<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
	public function run(): void
	{
		$defaults = [
			['key' => 'site.name', 'value' => 'Sibrama Admin', 'type' => 'string'],
			['key' => 'site.maintenance', 'value' => '0', 'type' => 'boolean'],
			['key' => 'site.meta', 'value' => json_encode(['description' => 'Admin Panel']), 'type' => 'json'],
		];
		foreach ($defaults as $item) {
			SiteSetting::updateOrCreate(['key' => $item['key']], ['value' => $item['value'], 'type' => $item['type']]);
		}
	}
}















