<?php

declare(strict_types=1);

namespace Cosray\Value;

use function Cosray\escape;

class Option extends Value
{
	public function __toString(): string
	{
		return escape($this->raw());
	}

	public function raw(): string
	{
		$value = $this->value();

		return is_string($value) || is_numeric($value) ? (string) $value : '';
	}

	/** @return array<string, mixed> */
	public function json(): array
	{
		return $this->data;
	}

	public function isset(): bool
	{
		return $this->raw() !== '';
	}
}
