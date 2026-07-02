<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Tests\Fixtures\Node\TestHierarchyParent;

#[Label('Test hierarchy'), Handle('test-hierarchy'), Listing(children: true)]
final class TestHierarchyCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms
			->nodes()
			->types('test-hierarchy-parent', 'test-hierarchy-child')
			->published(null)
			->hidden(null);
	}

	/** @return list<class-string> */
	public function blueprints(): array
	{
		return [TestHierarchyParent::class];
	}
}
