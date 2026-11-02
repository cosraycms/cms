<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets\ResizeMode;
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
		return (string) $this->img();
	}

	/**
	 * The `<img>`, empty when its file is gone. It carries the rendition's size,
	 * so the browser can keep the space. Further named arguments become
	 * attributes and can replace those, like `img(loading: 'lazy')`; null leaves
	 * one out. Spread an array for names with a dash.
	 */
	public function img(?string $class = null, string|int|null ...$attributes): Markup
	{
		$asset = $this->asset($this->index);

		if ($asset === null) {
			return new Markup('');
		}

		[$width, $height] = $this->dimensions();
		$position = $this->position();

		if ($position !== '' && is_string($attributes['style'] ?? null)) {
			$attributes['style'] = "{$position}; {$attributes['style']}";
		}

		$html = $this->printed([
			'class' => $class === '' ? null : $class,
			'src' => $this->publicPath(),
			...$attributes,
			...array_diff_key(
				[
					'width' => $width,
					'height' => $height,
					'alt' => $this->alt() ?: strip_tags($this->title()),
					'style' => $position === '' ? null : $position,
				],
				$attributes,
			),
		]);

		return new Markup("<img{$html}>");
	}

	/**
	 * The image in a `<figure>`, inside its link when it has one, with its
	 * caption and attribution when it has them. The figure stays without them,
	 * so pages keep the same structure.
	 *
	 * @param ?Markup $image Instead of the plain `img()`
	 */
	public function figure(?Markup $image = null, ?string $class = null): Markup
	{
		if ($this->asset($this->index) === null) {
			return new Markup('');
		}

		$text = (string) $this->caption();
		$attribution = $this->attribution();

		if ($attribution !== '') {
			$text = trim($text . ' <small>' . escape($attribution) . '</small>');
		}

		return new Markup(
			'<figure'
				. ($class === null || $class === '' ? '' : sprintf(' class="%s"', escape($class)))
				. '>'
				. $this->linked($image ?? $this->img())
				. ($text === '' ? '' : "<figcaption>{$text}</figcaption>")
				. '</figure>',
		);
	}

	/**
	 * The `srcset` of named sizes, by the width of each file, for `img(srcset: ...)`.
	 * Sizes that come out the same drop out, as a small image is not enlarged. It
	 * is null where there is nothing to choose from: an SVG, or an image of one width.
	 */
	public function srcset(string ...$sizes): ?string
	{
		$asset = $this->asset($this->index);

		if ($asset === null || !$asset->resizable() || $asset->mime === 'image/gif') {
			return null;
		}

		$entries = [];

		foreach ($sizes as $size) {
			$sized = $this->size($size);
			// A row from before dimensions were stored only knows what the size asks for.
			$width =
				$sized->dimensions()[0] ?? ($sized->spec?->mode === ResizeMode::Width ? $sized->spec->first : null);

			if ($width !== null && !isset($entries[$width])) {
				$entries[$width] = $sized->publicPath() . " {$width}w";
			}
		}

		ksort($entries);

		return count($entries) > 1 ? implode(', ', $entries) : null;
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

		return $asset->renditionPath($spec, $variant);
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

	/** The content, by default the `img()`, inside this use's link, or alone without one. */
	public function linked(?Markup $content = null): Markup
	{
		$content ??= $this->img();
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

		$stored = array_intersect_key($link, array_flip(['href', 'node', 'asset', 'target', 'rel']));

		/** @var ?array{href: string, target?: string, rel?: string} */
		return Link::attributes($stored, new OwnerResolver($this->owner));
	}

	/**
	 * The size of the file `publicPath()` leads to.
	 *
	 * @return array{?int, ?int}
	 */
	private function dimensions(): array
	{
		return (
			$this->asset($this->index)?->dimensions(
				$this->spec,
				$this->delivered() ? $this->transform() : null,
			) ?? [null, null]
		);
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
