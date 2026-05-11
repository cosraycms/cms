<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Label;

#[Label('Node With Custom Name Attribute')]
class NodeWithNameAttribute implements Title
{
	public function title(): string
	{
		return 'with name';
	}
}
