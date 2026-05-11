<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Route;

#[Label('Custom Node')]
#[Route('/custom/{uid}')]
#[CustomIcon('star')]
class NodeWithCustomAttribute implements Title
{
	public function title(): string
	{
		return 'custom';
	}
}
