<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;
use Cosray\Value\Images as ImagesValue;

use function Cosray\escape;

#[Label('block:images')]
final class Images implements Block
{
	private const string THUMB = 'block-thumb';

	#[Label('block:images'), Required, Translate]
	protected Field\Image $images;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		$name = (string) ($ctx->args['thumbSize'] ?? self::THUMB);
		// Validate the name up front — a typo should fail the render,
		// not silently emit URLs the fallback route will 404.
		$ctx->owner->config()->media->sizes->get($name);
		$prefix = $ctx->prefix();
		/** @var ImagesValue $images */
		$images = $block->images;
		$result = '';

		foreach ($images->unwrap() as $index => $item) {
			$asset = $ctx->asset((string) ($item['uid'] ?? ''));

			if ($asset === null) {
				continue;
			}

			$image = $images->get($index);
			$alt = escape($image->alt() ?: strip_tags($image->title()));
			$path = escape($asset->path());
			$url = $asset->resizable() ? escape($asset->sizePath($name)) : $path;

			$result .= "<div class=\"{$prefix}-blocks-images-image\"><img src=\"{$url}\" alt=\"{$alt}\" data-path-original=\"{$path}\"></div>";
		}

		if ($result === '') {
			return '';
		}

		return "<div class=\"{$prefix}-blocks-images\"{$this->settings($images)}>{$result}</div>";
	}

	/** The gallery settings as attributes: the ratio as data and custom property, the crop as a flag. */
	private function settings(ImagesValue $images): string
	{
		$attributes = '';
		$ratio = $images->ratio();

		if ($ratio !== null) {
			$ratio = escape($ratio);
			$attributes .= " data-ratio=\"{$ratio}\" style=\"--ratio: {$ratio}\"";
		}

		if ($images->crop()) {
			$attributes .= ' data-crop';
		}

		return $attributes;
	}
}
