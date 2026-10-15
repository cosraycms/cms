<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Labelable
{
	public function label(string $label): static;

	public function getLabel(): ?string;
}
