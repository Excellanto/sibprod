<?php

namespace App\Http\Controllers;

use App\Models\LanguageString;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class LanguageStringController extends Controller
{
	public function index(Request $request)
	{
		$this->authorize('viewAny', LanguageString::class);
		$lang = $request->string('lang')->toString() ?: app()->getLocale();
		$file = $request->string('file')->toString();

		$query = LanguageString::query()->where('lang', $lang);
		if ($file) {
			$query->where('file', $file);
		}

		$files = LanguageString::query()
			->select('file')
			->where('lang', $lang)
			->groupBy('file')
			->pluck('file');

		$rows = $query->orderBy('file')->orderBy('key')->paginate(100)->withQueryString();

		return Inertia::render('Admin/Languages/Index', [
			'lang' => $lang,
			'file' => $file,
			'files' => $files,
			'rows' => $rows,
		]);
	}

	public function update(Request $request)
	{
		$this->authorize('update', new LanguageString());
		$data = $request->validate([
			'lang' => ['required', 'string'],
			'items' => ['required', 'array'],
			'items.*.file' => ['required', 'string'],
			'items.*.key' => ['required', 'string'],
			'items.*.value' => ['nullable', 'string'],
		]);

		foreach ($data['items'] as $item) {
			LanguageString::updateOrCreate(
				['lang' => $data['lang'], 'file' => $item['file'], 'key' => $item['key']],
				['value' => $item['value'] ?? '']
			);
		}

		return back()->with('success', 'Translations updated.');
	}

	public function syncLanguageFilesToDB(Request $request)
	{
		$this->authorize('sync', new LanguageString());

		$base = resource_path('lang');
		$languages = collect(File::directories($base))
			->map(fn ($path) => basename($path))
			->filter();

		foreach ($languages as $lang) {
			$dir = $base . DIRECTORY_SEPARATOR . $lang;
			$phpFiles = collect(File::files($dir))->filter(fn ($f) => $f->getExtension() === 'php');
			foreach ($phpFiles as $file) {
				$group = pathinfo($file->getFilename(), PATHINFO_FILENAME);
				$lines = include $file->getPathname();
				$flattened = Arr::dot($lines);
				foreach ($flattened as $key => $value) {
					LanguageString::firstOrCreate(
						['lang' => $lang, 'file' => $group, 'key' => $key],
						['value' => is_string($value) ? $value : json_encode($value)]
					);
				}
			}
		}

		return back()->with('success', 'Language files synced to database.');
	}
}















