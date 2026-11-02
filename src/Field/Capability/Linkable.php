<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Linkable
{
	public function linkable(bool $linkable = true): static;

	public function isLinkable(): bool;
}
