<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\Attribution;
use Cosray\Schema\Caption;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;
use Cosray\Value\Images as ImagesValue;
use Override;

#[Label('block:images')]
final class Images implements Block
{
	private const string THUMB = 'block-thumb';

	#[Label('block:images'), Required, Translate, Caption, Attribution]
	protected Field\Image $images;

	#[Override]
	public function render(BlockValue $block, RenderContext $ctx): string
	{
		/** @var ImagesValue $images */
		$images = $block->images;
		$large = $ctx->args['largeSize'] ?? null;

		return (string) $images->gallery(
			(string) ($ctx->args['thumbSize'] ?? self::THUMB),
			is_string($large) ? $large : null,
			$ctx->prefix(),
		);
	}
}
