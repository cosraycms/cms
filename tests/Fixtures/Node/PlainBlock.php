<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Schema\Deletable;
use Celemas\Cms\Schema\Label;

#[Label('Plain Block')]
#[Deletable(false)]
class PlainBlock
{
	#[Label('Content')]
	protected Text $content;
}
