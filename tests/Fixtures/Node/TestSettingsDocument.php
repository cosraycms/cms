<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Blocks;
use Cosray\Field\Text;
use Cosray\Schema\Allows;
use Cosray\Schema\Immutable;
use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Block\ActionBlock;
use Cosray\Tests\Fixtures\Block\SpacerBlock;

#[Label('Test Settings Document')]
class TestSettingsDocument implements Title
{
	#[Label('Title')]
	public Text $title;

	#[Label('Blocks'), Allows(ActionBlock::class, SpacerBlock::class)]
	public Blocks $blocks;

	#[Label('Fixed'), Allows(ActionBlock::class), Immutable]
	public Blocks $fixed;

	public function title(): string
	{
		return $this->title?->value()->raw() ?? 'Test Settings Document';
	}
}
