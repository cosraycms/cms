<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Render;

#[Render('template-defined-by-render-attribute')]
class NodeWithRenderAttribute implements Title
{
	public function title(): string
	{
		return 'with render';
	}
}
