<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

interface Resizable
{
	public function width(int $width): static;

	public function getWidth(): int;

	public function rows(int $rows): static;

	public function getRows(): int;
}
