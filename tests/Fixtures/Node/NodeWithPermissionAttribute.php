<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Permission;

#[Permission([
	'read' => 'me',
])]
class NodeWithPermissionAttribute implements Title
{
	public function title(): string
	{
		return 'with permission';
	}
}
