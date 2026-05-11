<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Assets;

class TranslatedFile extends File
{
	public function isset(): bool
	{
		$locale = $this->locale;

		while ($locale) {
			$value = $this->data['files'][$locale->id][$this->index]['file'] ?? null;

			if ($value) {
				return true;
			}

			$locale = $locale->fallback();
		}

		return false;
	}

	protected function textValue(string $key, int $index): string
	{
		return $this->translated($key, $index);
	}

	protected function translated(string $key, int $index): string
	{
		$locale = $this->locale;

		while ($locale) {
			$value = $this->data['files'][$locale->id][$index][$key] ?? null;

			if ($value) {
				return $value;
			}

			$locale = $locale->fallback();
		}

		return '';
	}

	protected function getFile(int $index): Assets\File
	{
		$file = $this->translated('file', $index);

		return $this->getAssets()->file($this->assetsPath() . $file);
	}
}
