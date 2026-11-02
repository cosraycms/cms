<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets\Sizes;
use Cosray\Assets\SizeSpec;
use Cosray\Assets\Transform;
use Cosray\Markup;
use Cosray\Richtext\Link;
use Cosray\Richtext\OwnerResolver;
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
			'<img %ssrc="%s" alt="%s" data-path-original="%s"%s>',
			$class ? sprintf('class="%s" ', escape($class)) : '',
			$this->url(),
			escape($this->alt() ?: strip_tags($this->title())),
			$this->asset($this->index)?->path() ?? '',
			($position = $this->position()) === '' ? '' : sprintf(' style="%s"', $position),
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

		if (!$asset->resizable()) {
			return $asset->path();
		}

		$secret = $this->owner->config()->app->secret;
		$spec = $this->spec ?? Sizes::original();
		$variant = $secret === null ? '' : $this->transform()?->variant($asset->uid, $spec, $secret) ?? '';

		if ($this->spec === null && $variant === '') {
			return $asset->path();
		}

		return $asset->sizePath($spec->name, $variant);
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
			$sizes[$name] = $this->size($name)->publicPath();
		}

		$crop = $this->delivered() ? $this->transform()?->crop : null;

		return [
			...$file,
			'alt' => $this->alt(),
			'attribution' => $this->attribution(),
			'link' => $this->anchor(),
			'width' =>
				$crop === null || $asset->width === null ? $asset->width : (int) round($asset->width * $crop['w']),
			'height' =>
				$crop === null || $asset->height === null ? $asset->height : (int) round($asset->height * $crop['h']),
			'focal' => $this->focal(),
			'sizes' => $sizes,
		];
	}

	/** The address this use links to; empty without a link or when its target is gone. */
	public function link(): string
	{
		return $this->anchor()['href'] ?? '';
	}

	/** The content, by default the image's tag, inside this use's link, or alone without one. */
	public function linked(?Markup $content = null): Markup
	{
		$content ??= $this->tag();
		$anchor = $this->anchor();

		if ($anchor === null) {
			return $content;
		}

		$attributes = '';

		foreach ($anchor as $name => $value) {
			$attributes .= sprintf(' %s="%s"', $name, escape($value));
		}

		return new Markup("<a{$attributes}>{$content}</a>");
	}

	public function alt(): string
	{
		return $this->textValue('alt', $this->index);
	}

	/** Who made the image: per use, else the catalog's. Neutral, as names do not translate. */
	public function attribution(): string
	{
		$own = $this->fileItem($this->index)['meta']['attribution'] ?? null;

		if (is_string($own) && $own !== '') {
			return $own;
		}

		$catalog = $this->asset($this->index)?->meta['attribution'] ?? null;

		return is_string($catalog) ? $catalog : '';
	}

	/**
	 * The focal point in the image a visitor gets, as fractions: this use's,
	 * else the catalog's.
	 *
	 * @return ?array{x: float, y: float}
	 */
	public function focal(): ?array
	{
		$transform = $this->transform();

		return $this->delivered() ? $transform?->focalInCrop() : $transform?->focal;
	}

	/** The focal point for a style attribute of an image cut by CSS; empty without one. */
	public function position(): string
	{
		$focal = $this->focal();

		if ($focal === null) {
			return '';
		}

		return sprintf('object-position: %s%% %s%%', round($focal['x'] * 100, 2), round($focal['y'] * 100, 2));
	}

	/** @return ?array{href: string, target?: string, rel?: string} */
	private function anchor(): ?array
	{
		$link = $this->fileItem($this->index)['meta']['link'] ?? null;

		if (!is_array($link)) {
			return null;
		}

		$stored = array_intersect_key($link, array_flip(['href', 'node', 'asset', 'target']));

		/** @var ?array{href: string, target?: string, rel?: string} */
		return Link::attributes($stored, new OwnerResolver($this->owner));
	}

	private function transform(): ?Transform
	{
		$meta = $this->fileItem($this->index)['meta'] ?? null;
		$meta = is_array($meta) ? $meta : [];

		return Transform::from(
			$meta['crop'] ?? null,
			$meta['focal'] ?? $this->asset($this->index)?->meta['focal'] ?? null,
		);
	}

	/** Whether a crop reaches the visitor; it takes a rendition, and that a signature. */
	private function delivered(): bool
	{
		return (
			($this->asset($this->index)?->resizable() ?? false)
				&& $this->owner->config()->app->secret !== null
		);
	}
}
