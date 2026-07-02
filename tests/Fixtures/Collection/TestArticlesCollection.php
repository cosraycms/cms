<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;

#[Label('Test articles'), Handle('test-articles')]
final class TestArticlesCollection extends Collection
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
