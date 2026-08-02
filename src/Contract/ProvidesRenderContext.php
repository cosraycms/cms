<?php

declare(strict_types=1);

namespace Cosray\Contract;

interface ProvidesRenderContext
{
	public function renderContext(): array;
}
