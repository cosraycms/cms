<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Children;

#[Children(PlainPage::class, PlainBlock::class)]
class NodeWithChildrenAttribute implements Title
{
	public function title(): string
	{
		return 'children';
	}
}
