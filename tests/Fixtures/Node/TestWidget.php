<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Label;

#[Label('Test Widget')]
class TestWidget implements Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Test Widget';
	}
}
