<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Multiline
{
	public function lines(int $lines): static;

	public function getLines(): ?int;
}
