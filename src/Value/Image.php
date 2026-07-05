<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets\SizeSpec;

use function Cosray\escape;

class Image extends File
{
	protected ?SizeSpec $spec = null;

	public function __toString(): string
	{
		return $this->tag();
	}

	public function tag(?string $class = null): string
	{
		return sprintf(
			'<img %ssrc="%s" alt="%s" data-path-original="%s">',
			$class ? sprintf('class="%s" ', escape($class)) : '',
			$this->url(),
			escape($this->alt() ?: strip_tags($this->title())),
			$this->asset($this->index)?->path() ?? '',
		);
	}

	/**
	 * Use a named rendition from the `media.sizes` config. Unknown names
	 * throw immediately; assets the resize pipeline cannot process (SVG,
	 * video posters) keep their original URL.
	 */
	public function size(string $name): static
	{
		$new = clone $this;
		$new->spec = $this->owner->config()->media->sizes->get($name);

		return $new;
	}

	public function publicPath(): string
	{
		$asset = $this->asset($this->index);

		if ($asset === null) {
			return '';
		}

		if ($this->spec !== null && $asset->resizable()) {
			return $asset->sizePath($this->spec->name);
		}

		return $asset->path();
	}

	public function link(): string
	{
		return $this->textValue('link', $this->index);
	}

	public function alt(): string
	{
		return $this->textValue('alt', $this->index);
	}
}
