<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Schema\Title;

class NodeWithInvalidPropertyTitleAttribute
{
	#[Title]
	protected string $heading;
}
