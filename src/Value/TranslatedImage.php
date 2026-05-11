<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Assets;

class TranslatedImage extends Image
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

	protected function getMediaPath(int $index): string
	{
		return (
			$this->owner->config()->path->prefix
			. '/media/image/'
			. $this->assetsPath()
			. $this->translated('file', $index)
			. $this->queryString
			. ($this->quality ? "&quality={$this->quality}" : '')
		);
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

	protected function getImage(int $index): Assets\Image
	{
		$file = $this->translated('file', $index);
		$image = $this->getAssets()->image($this->assetsPath() . $file);

		if ($this->size) {
			$image = $image->resize(
				$this->size,
				$this->resizeMode,
				$this->enlarge,
				$this->quality,
			);
		}

		return $image;
	}
}
