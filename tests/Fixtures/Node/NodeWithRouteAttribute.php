<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Route;

#[Route('/node-with-custom/{route}')]
class NodeWithRouteAttribute implements Title
{
	public function title(): string
	{
		return 'with route';
	}
}
