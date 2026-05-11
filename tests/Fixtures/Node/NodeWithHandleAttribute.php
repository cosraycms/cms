<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Handle;

#[Handle('node-with-custom-handle-attribute')]
class NodeWithHandleAttribute implements Title
{
	public function title(): string
	{
		return 'with handle';
	}
}
