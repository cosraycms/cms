<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Permission;

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
