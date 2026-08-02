<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Route;

#[Route('/node-with-custom/{route}')]
class NodeWithRouteAttribute implements Title
{
	public function title(): string
	{
		return 'with route';
	}
}
