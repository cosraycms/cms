<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

interface Defaultable
{
	public function default(mixed $default): static;
}
