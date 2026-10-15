<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Selectable
{
	/** @param string|array<string, mixed> $option */
	public function add(string|array $option): void;

	/** @param array<string|array<string, mixed>> $options */
	public function options(array $options): void;

	/** @return array<string|array<string, mixed>> */
	public function getOptions(): array;
}
