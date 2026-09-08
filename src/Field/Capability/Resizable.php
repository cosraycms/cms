<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Resizable
{
	public function width(int $width): static;

	public function getWidth(): int;

	public function rowspan(int $rowspan): static;

	public function getRowspan(): ?int;
}
