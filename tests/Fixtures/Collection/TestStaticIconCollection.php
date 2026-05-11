<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Collection;

use Celemas\Cms\Collection;
use Celemas\Cms\Finder\Nodes;

final class TestStaticIconCollection extends Collection
{
	protected static string $name = 'Static icon';
	protected static string $handle = 'test-static-icon';
	protected static ?string $icon = 'bi:archive';

	public function entries(): Nodes
	{
		return $this->cms
			->nodes()
			->types('test-article')
			->published(null)
			->hidden(null);
	}
}
