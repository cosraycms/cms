<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Node\ParentPathRoutePage;

#[Label('Test routable'), Handle('test-routable')]
final class TestRoutableCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms
			->nodes()
			->types('parent-path-route-page')
			->published(null)
			->hidden(null);
	}

	/** @return list<class-string> */
	public function blueprints(): array
	{
		return [ParentPathRoutePage::class];
	}
}
