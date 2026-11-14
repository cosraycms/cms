<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Markup;
use Override;

use function Cosray\escape;

class Images extends Files
{
	#[Override]
	public function __toString(): string
	{
		$out = '';

		for ($i = 0; $i < count($this->files()); $i++) {
			$out .= (string) $this->get($i);
		}

		return $out;
	}

	#[Override]
	public function current(): Image
	{
		return $this->get($this->pointer);
	}

	#[Override]
	public function get(int $index): Image
	{
		return new Image($this->owner, $this->field, $this->context, $index);
	}

	#[Override]
	public function first(): Image
	{
		return new Image($this->owner, $this->field, $this->context, 0);
	}

	/**
	 * The images as a gallery: a figure per image in a wrapper that carries the
	 * field's ratio and crop settings. `$large` adds another rendition's URL as
	 * `data-url-large`, for a lightbox. Empty when no image can be shown.
	 */
	public function gallery(
		string $size = 'block-thumb',
		?string $large = null,
		string $prefix = 'cosray',
		string|int|null ...$attributes,
	): Markup {
		$sizes = $this->owner->config()->media->sizes;
		// A typo should fail the render rather than emit URLs that lead nowhere.
		$sizes->get($size);

		if ($large !== null) {
			$sizes->get($large);
		}

		$items = '';

		foreach ($this as $image) {
			$sized = $image->size($size);
			$extra = $large === null ? [] : ['data-url-large' => $image->size($large)->publicPath()];
			$items .= $sized->figure(
				$sized->img(null, ...['loading' => 'lazy', ...$extra, ...$attributes]),
				"{$prefix}-image-gallery-item",
			);
		}

		if ($items === '') {
			return new Markup('');
		}

		$attributes = sprintf('class="%s-image-gallery"', escape($prefix));
		$ratio = $this->ratio();

		if ($ratio !== null) {
			$ratio = escape($ratio);
			$attributes .= " data-ratio=\"{$ratio}\" style=\"--ratio: {$ratio}\"";
		}

		if ($this->crop()) {
			$attributes .= ' data-crop';
		}

		return new Markup("<div {$attributes}>{$items}</div>");
	}

	/** The gallery tiles' aspect ratio, such as `4/3`; `null` keeps each image's own. */
	public function ratio(): ?string
	{
		$ratio = $this->meta('ratio');

		return is_string($ratio) && $ratio !== '' && $ratio !== 'auto' ? $ratio : null;
	}

	/** Whether the gallery tiles crop to their ratio instead of fitting into it. */
	public function crop(): bool
	{
		return (bool) $this->meta('crop', false);
	}
}
