<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Handle;

#[Handle('node-with-custom-handle-attribute')]
class NodeWithHandleAttribute implements Title
{
	public function title(): string
	{
		return 'with handle';
	}
}
