<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Schema\Title;

class NodeWithPropertyTitleAttribute
{
	#[Title]
	protected Text $heading;

	protected Text $body;
}
