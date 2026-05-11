<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Number;
use Celemas\Cms\Schema\Title;

#[Title('count')]
class NodeWithNumericTitleField
{
	public Number $count;
}
