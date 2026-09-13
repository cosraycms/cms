<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Node\TestDraftPage;

#[Label('Test draft pages'), Handle('test-draft-pages')]
final class TestDraftPagesCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms
			->nodes()
			->types('test-draft-page')
			->published(null)
			->hidden(null);
	}

	/** @return list<class-string> */
	public function blueprints(): array
	{
		return [TestDraftPage::class];
	}
}
