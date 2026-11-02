<?php

namespace App\Console\Commands;

use App\Models\LanguageString;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class LanguageKeySyncCommand extends Command
{
	protected $signature = 'lang:sync';
	protected $description = 'Sync language keys from resources/lang/* into database';

	public function handle(): int
	{
		$base = resource_path('lang');
		if (!File::exists($base)) {
			$this->warn('No language resources found.');
			return self::SUCCESS;
		}

		$languages = collect(File::directories($base))->map(fn ($path) => basename($path));
		$count = 0;
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
					$count++;
				}
			}
		}

		$this->info("Synced {$count} language keys.");
		return self::SUCCESS;
	}
}















