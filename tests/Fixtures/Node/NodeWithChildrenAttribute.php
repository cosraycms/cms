<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Children;

#[Children(PlainPage::class, PlainBlock::class)]
class NodeWithChildrenAttribute implements Title
{
	public function title(): string
	{
		return 'children';
	}
}
