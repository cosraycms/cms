<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;

#[Label('block:video')]
final class Video implements Block
{
	#[Label('block:video'), Required, Limit(1), Translate]
	protected Field\Video $video;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		$video = $block->video;

		if ($ctx->asset((string) ($video->unwrap()['uid'] ?? '')) === null) {
			return '';
		}

		return (string) $video;
	}
}
