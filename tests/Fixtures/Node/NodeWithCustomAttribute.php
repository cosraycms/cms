<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Label;
use Cosray\Schema\Route;

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
