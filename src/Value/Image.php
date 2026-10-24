<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets\SizeSpec;
use Cosray\Markup;
use Override;

use function Cosray\escape;

class Image extends File
{
	protected ?SizeSpec $spec = null;

	#[Override]
	public function __toString(): string
	{
		return (string) $this->tag();
	}

	public function tag(?string $class = null): Markup
	{
		return new Markup(sprintf(
			'<img %ssrc="%s" alt="%s" data-path-original="%s">',
			$class ? sprintf('class="%s" ', escape($class)) : '',
			$this->url(),
			escape($this->alt() ?: strip_tags($this->title())),
			$this->asset($this->index)?->path() ?? '',
		));
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

	#[Override]
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

	/**
	 * Adds the image facts and a URL for every named size; an asset the
	 * resize pipeline cannot process gives its original URL for each.
	 *
	 * @return ?array<string, mixed>
	 */
	#[Override]
	public function json(): ?array
	{
		$file = parent::json();
		$asset = $this->asset($this->index);

		if ($file === null || $asset === null) {
			return null;
		}

		$sizes = [];

		foreach ($this->owner->config()->media->sizes->names() as $name) {
			$sizes[$name] = $asset->resizable() ? $asset->sizePath($name) : $asset->path();
		}

		$focal = $asset->meta['focal'] ?? null;

		return [
			...$file,
			'alt' => $this->alt(),
			'link' => $this->link(),
			'width' => $asset->width,
			'height' => $asset->height,
			'focal' => is_array($focal) ? $focal : null,
			'sizes' => $sizes,
		];
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
