<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Tests\Fixtures\Node\OptionalParentPathRoutePage;

#[Label('Test routable hierarchy'), Handle('test-routable-hierarchy'), Listing(children: true)]
final class TestRoutableHierarchyCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms
			->nodes()
			->types('optional-parent-path-route-page')
			->published(null)
			->hidden(null);
	}

	/** @return list<class-string> */
	public function blueprints(): array
	{
		return [OptionalParentPathRoutePage::class];
	}
}
