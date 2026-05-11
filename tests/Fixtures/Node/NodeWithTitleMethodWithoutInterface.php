<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;

class NodeWithTitleMethodWithoutInterface
{
	public Text $title;

	public function title(): string
	{
		return 'Method title should not be used';
	}
}
