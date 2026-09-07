<?php

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Text;
use Cosray\Schema\Handle;
use Cosray\Schema\Icon;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;

#[Label('block:heading'), Handle('catalog-note'), Icon('test:note', size: 20)]
final class CatalogBlock implements Block
{
	#[Required, Translate]
	protected Text $text;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return (string) $block->text;
	}
}
