<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets;

use function Cosray\escape;

class TranslatedPicture extends Picture
{
	public function tag(bool $bust = true, ?string $class = null): string
	{
		$class = $class ? sprintf(' class="%s" ', escape($class, ENT_QUOTES, 'UTF-8')) : '';
		$sources = '';
		$lastIndex = 0;

		foreach ($this->localizedFiles() as $index => $image) {
			$sources .= sprintf(
				'<source %s srcset="%s">',
				$this->getSourceAttr($image),
				$this->url($bust, $index),
			);
			$lastIndex = $index;
		}

		$img = sprintf(
			'<img src="%s" alt="%s">',
			$this->url($bust, $lastIndex),
			escape($this->alt() ?: strip_tags($this->title())),
		);

		return sprintf('<picture%s>%s%s</picture>', $class, $sources, $img);
	}

	public function isset(): bool
	{
		return $this->localizedFiles() !== [];
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

			if (is_string($value) && $value !== '') {
				return $value;
			}

			if (is_int($value) || is_float($value)) {
				return (string) $value;
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

	private function localizedFiles(): array
	{
		$locale = $this->locale;

		while ($locale) {
			$files = $this->data['files'][$locale->id] ?? [];

			if (is_array($files) && ($files[0]['file'] ?? null)) {
				return $files;
			}

			$locale = $locale->fallback();
		}

		return [];
	}
}
