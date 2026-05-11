<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures;

use Celemas\Cms\Renderer;
use Override;

final class StaticRenderer implements Renderer
{
	#[Override]
	public function render(string $id, array $context): string
	{
		return 'custom:' . $id;
	}

	#[Override]
	public function contentType(): string
	{
		return 'text/plain';
	}
}
