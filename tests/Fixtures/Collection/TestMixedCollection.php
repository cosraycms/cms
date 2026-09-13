<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Collection;
use Cosray\Finder\Nodes;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Node\TestArticle;
use Cosray\Tests\Fixtures\Node\TestPage;

#[Label('Test mixed'), Handle('test-mixed')]
final class TestMixedCollection extends Collection
{
	public function entries(): Nodes
	{
		return $this->cms
			->nodes()
			->types('test-page', 'test-article')
			->published(null)
			->hidden(null);
	}

	/** @return list<class-string> */
	public function blueprints(): array
	{
		return [TestPage::class, TestArticle::class];
	}
}
