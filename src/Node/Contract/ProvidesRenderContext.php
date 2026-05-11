<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Contract;

interface ProvidesRenderContext
{
	public function renderContext(): array;
}
