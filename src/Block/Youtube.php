<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Value\Block as BlockValue;

#[Label('block:youtube')]
final class Youtube implements Block
{
	#[Label('block:youtube'), Required]
	protected Field\Youtube $video;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		$video = $block->video;

		return $video->isset() ? (string) $video : '';
	}
}
