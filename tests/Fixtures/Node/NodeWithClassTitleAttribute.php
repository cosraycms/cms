<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Schema\Title;

#[Title('heading')]
class NodeWithClassTitleAttribute
{
	protected Text $heading;
}
