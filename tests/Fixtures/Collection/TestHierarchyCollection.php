<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Tests\Fixtures\Node\TestHierarchyParent;

final class TestHierarchyCollection extends Collection
{
	protected static string $name = 'Test hierarchy';
	protected static string $handle = 'test-hierarchy';
	protected static bool $showChildren = true;

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
