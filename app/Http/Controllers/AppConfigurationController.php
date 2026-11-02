<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AppConfigurationController extends Controller
{
	public function index()
	{
		$user = auth()->user();
		
		// Check authorization - admin users or users with app-configuration.view permission
		if (!$user->hasRole('Admin') && !$user->hasRole('admin') && !$user->hasRole('Administrator') && !$user->can('app-configuration.view')) {
			abort(403, 'This action is unauthorized.');
		}
		
		$settings = SiteSetting::whereIn('key', ['site.name', 'site.logo', 'site.favicon'])
			->pluck('value', 'key')
			->toArray();

		return Inertia::render('Admin/AppConfiguration/Index', [
			'title' => $settings['site.name'] ?? '',
			'logo' => $settings['site.logo'] ?? null,
			'favicon' => $settings['site.favicon'] ?? null,
		]);
	}

	public function update(Request $request)
	{
		$user = auth()->user();
		
		// Check authorization - admin users or users with app-configuration.update permission
		if (!$user->hasRole('Admin') && !$user->hasRole('admin') && !$user->hasRole('Administrator') && !$user->can('app-configuration.update')) {
			abort(403, 'This action is unauthorized.');
		}

		$data = $request->validate([
			'title' => ['required', 'string', 'max:255'],
			'logo' => ['nullable', 'file', 'max:2048'],
			'favicon' => ['nullable', 'file', 'max:512'],
			'remove_logo' => ['nullable', 'boolean'],
			'remove_favicon' => ['nullable', 'boolean'],
		]);

		if ($request->hasFile('logo')) {
			$this->assertAllowedUpload($request->file('logo'), ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'], 'logo');
		}

		if ($request->hasFile('favicon')) {
			$this->assertAllowedUpload($request->file('favicon'), ['ico', 'png', 'jpg', 'jpeg', 'gif', 'webp'], 'favicon');
		}

		Cache::forget('app_settings');

		SiteSetting::updateOrCreate(
			['key' => 'site.name'],
			['value' => $data['title'], 'type' => 'string']
		);

		if ($request->boolean('remove_logo')) {
			$this->deleteSettingFile('site.logo');
		} elseif ($request->hasFile('logo')) {
			$this->storeSettingFile('site.logo', $request->file('logo'));
		}

		if ($request->boolean('remove_favicon')) {
			$this->deleteSettingFile('site.favicon');
		} elseif ($request->hasFile('favicon')) {
			$this->storeSettingFile('site.favicon', $request->file('favicon'));
		}

		Cache::forget('app_settings');

		return redirect()->route('admin.app-configuration.index')->with('success', 'App configuration updated successfully.');
	}

	private function storeSettingFile(string $key, UploadedFile $file): void
	{
		$current = SiteSetting::where('key', $key)->first();
		if ($current && $current->value && Storage::disk('public')->exists($current->value)) {
			Storage::disk('public')->delete($current->value);
		}

		$path = $file->store('config', 'public');
		if (! $path || ! Storage::disk('public')->exists($path)) {
			throw ValidationException::withMessages([
				$key === 'site.logo' ? 'logo' : 'favicon' => 'The file could not be saved.',
			]);
		}

		SiteSetting::updateOrCreate(
			['key' => $key],
			['value' => $path, 'type' => 'string']
		);
	}

	private function deleteSettingFile(string $key): void
	{
		$current = SiteSetting::where('key', $key)->first();
		if ($current && $current->value && Storage::disk('public')->exists($current->value)) {
			Storage::disk('public')->delete($current->value);
		}
		SiteSetting::where('key', $key)->delete();
	}

	private function assertAllowedUpload(UploadedFile $file, array $extensions, string $field): void
	{
		$extension = strtolower($file->getClientOriginalExtension() ?: '');
		$guessed = strtolower($file->guessExtension() ?: '');
		$mime = strtolower($file->getMimeType() ?: '');

		$extensionOk = in_array($extension, $extensions, true) || in_array($guessed, $extensions, true);
		$svgMime = $extension === 'svg' && in_array($mime, ['image/svg+xml', 'text/xml', 'text/plain', 'text/html'], true);
		$iconMime = $extension === 'ico' && in_array($mime, ['image/x-icon', 'image/vnd.microsoft.icon', 'application/octet-stream'], true);
		$mimeOk = str_starts_with($mime, 'image/') || $svgMime || $iconMime;

		if (! $extensionOk || ! $mimeOk) {
			throw ValidationException::withMessages([
				$field => 'Upload a '.implode(', ', $extensions).' file.',
			]);
		}
	}
}

