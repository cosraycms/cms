<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Icon;
use Cosray\Schema\Label;

#[Label('Static icon'), Handle('test-static-icon'), Icon('bi:archive')]
final class TestStaticIconCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms
			->nodes()
			->types('test-article')
			->published(null)
			->hidden(null);
	}
}
