<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiteSettingController extends Controller
{
	public function index()
	{
		$this->authorize('viewAny', SiteSetting::class);
		$settings = SiteSetting::query()->orderBy('key')->get();
		return Inertia::render('Admin/Settings/Index', [
			'settings' => $settings
		]);
	}

	public function update(Request $request)
	{
		$this->authorize('update', new SiteSetting());
		$data = $request->validate([
			'settings' => ['required', 'array'],
			'settings.*.key' => ['required', 'string'],
			'settings.*.value' => ['nullable'],
			'settings.*.type' => ['required', 'in:string,json,boolean'],
		]);

		foreach ($data['settings'] as $item) {
			$value = $item['value'];
			if ($item['type'] === 'json') {
				if (is_string($value)) {
					json_decode($value);
					if (json_last_error() !== JSON_ERROR_NONE) {
						continue;
					}
				}
			}
			if ($item['type'] === 'boolean') {
				$value = filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
			}
			SiteSetting::updateOrCreate(
				['key' => $item['key']],
				['value' => is_string($value) ? $value : json_encode($value), 'type' => $item['type']]
			);
		}

		cache()->forget('app_settings');

		return back()->with('success', 'Settings saved.');
	}
}















