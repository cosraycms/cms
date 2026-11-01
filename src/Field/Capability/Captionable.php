<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Captionable
{
	public function caption(bool $caption = true): static;

	public function hasCaption(): bool;
}
