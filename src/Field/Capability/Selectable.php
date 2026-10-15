<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Selectable
{
	public function add(string|array $option): void;

	public function options(array $options): void;

	public function getOptions(): array;
}
