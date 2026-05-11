<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

interface Immutable
{
	public function immutable(bool $immutable = true): static;
}
