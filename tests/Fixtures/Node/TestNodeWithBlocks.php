<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Block\Heading;
use Cosray\Contract\Title;
use Cosray\Field\Blocks;
use Cosray\Field\Text;
use Cosray\Schema\Allows;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Tests\Fixtures\Block\QuoteBlock;

/** A symmetric one-column blocks field: one shared list, translated sub-fields. */
#[Label('Test Node With Blocks')]
class TestNodeWithBlocks implements Title
{
	#[Label('Title'), Required]
	protected Text $title;

	#[Label('Blocks'), Translate]
	#[Allows(QuoteBlock::class, Heading::class)]
	protected Blocks $blocks;

	public function title(): string
	{
		return $this->title->value()->unwrap() ?? '';
	}
}
