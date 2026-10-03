<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Schema\Render;
use RuntimeException;

#[Render('template-defined-by-render-attribute')]
class NodeWithFailingTitle implements Title
{
	public function title(): string
	{
		throw new RuntimeException('Title failed');
	}
}
