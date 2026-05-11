<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Exception\RuntimeException;

use function Celemas\Cms\escape;

class Picture extends Image
{
	public function tag(bool $bust = true, ?string $class = null): string
	{
		$class = $class ? sprintf(' class="%s" ', escape($class, ENT_QUOTES, 'UTF-8')) : '';
		$sources = '';

		foreach ($this->data['files'] as $index => $image) {
			$sources .= sprintf(
				'<source %s srcset="%s">',
				$this->getSourceAttr($image),
				$this->url($bust, $index),
			);
		}

		// The last one is the fallback
		$img = sprintf(
			'<img src="%s" alt="%s">',
			$this->url($bust, $index),
			escape($this->alt() ?: strip_tags($this->title())),
		);

		return sprintf('<picture%s>%s%s</picture>', $class, $sources, $img);
	}

	public function url(bool $bust = true, int $index = 0): string
	{
		if ($url = filter_var($this->getImage($index)->url($bust), FILTER_VALIDATE_URL)) {
			return $url;
		}

		throw new RuntimeException('Invalid image url');
	}

	public function link(): string
	{
		return $this->textValue('link', 0);
	}

	public function title(): string
	{
		return $this->textValue('title', 0);
	}

	public function alt(): string
	{
		return $this->textValue('alt', 0);
	}

	protected function getSourceAttr(array $image): string
	{
		if (isset($image['media'])) {
			return sprintf(
				'media="%s"',
				escape($image['media']),
			);
		}

		return sprintf(
			'type="%s"',
			[
				'jpg' => 'image/jpeg',
				'png' => 'image/png',
				'gif' => 'image/gif',
				'svg' => 'image/svg+xml',
				'apng' => 'image/apng',
				'avif' => 'image/avif',
				'jpeg' => 'image/jpeg',
				'jfif' => 'image/jpeg',
				'pjpeg' => 'image/jpeg',
				'pjp' => 'image/jpeg',
				'webp' => 'image/webp',
			][strtolower(pathinfo($image['file'], PATHINFO_EXTENSION))],
		);
	}
}
