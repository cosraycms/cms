<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Collection;

use Celemas\Cms\Collection;
use Celemas\Cms\Finder\Nodes;
use Celemas\Cms\Tests\Fixtures\Node\TestHierarchyChild;

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
		return [TestHierarchyChild::class];
	}
}
