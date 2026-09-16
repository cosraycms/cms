<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Node\TestNodeWithBlocks;

/** A collection whose blueprint carries a blocks field. */
#[Label('Test blocks'), Handle('test-blocks')]
final class TestBlocksCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms
			->nodes()
			->types('test-node-with-blocks')
			->published(null)
			->hidden(null);
	}

	/** @return list<class-string> */
	public function blueprints(): array
	{
		return [TestNodeWithBlocks::class];
	}
}
